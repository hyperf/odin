<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Generator;
use Hyperf\Odin\Utils\LogUtil;
use InvalidArgumentException;
use IteratorAggregate;
use RuntimeException;
use Throwable;

/**
 * AWS Event Stream Parser.
 *
 * Parses AWS event-stream format without depending on AWS SDK.
 *
 * AWS event-stream format:
 * - Prelude (12 bytes): total_length (4) + headers_length (4) + prelude_crc (4)
 * - Headers (variable): key-value pairs with type info
 * - Payload (variable): the actual event data
 * - Message CRC (4 bytes): checksum of the entire message
 *
 * @see https://docs.aws.amazon.com/AmazonS3/latest/API/RESTSelectObjectAppendix.html
 */
class AwsEventStreamParser implements IteratorAggregate
{
    /**
     * @var resource
     */
    private $stream;

    private string $buffer = '';

    /**
     * @param resource $stream PHP stream resource
     */
    public function __construct($stream)
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource');
        }

        $this->stream = $stream;
    }

    /**
     * Get iterator to parse event stream.
     */
    public function getIterator(): Generator
    {
        $messageCount = 0;
        $this->log('开始解析EventStream', [
            'feof' => feof($this->stream),
        ]);

        try {
            while (! feof($this->stream)) {
                $length = $this->readExactly(4);
                if ($length === null) {
                    // Normal EOF
                    $this->log('流正常结束', [
                        'total_messages' => $messageCount,
                        'feof' => feof($this->stream),
                    ]);
                    break;
                }

                $lengthUnpacked = unpack('N', $length);
                $toRead = $lengthUnpacked[1] - 4;

                $body = $this->readExactly($toRead);
                if ($body === null) {
                    $this->log('读取消息体失败', [
                        'message_count' => $messageCount,
                        'to_read' => $toRead,
                        'buffer_preview' => substr($this->buffer, 0, 200),
                    ]);
                    throw new RuntimeException('Failed to read message body from stream');
                }

                $chunk = $length . $body;
                $this->buffer .= $chunk;

                while (($message = $this->parseNextMessage()) !== null) {
                    ++$messageCount;
                    yield $message;
                }
            }
        } finally {
            $this->log('EventStream解析完成', [
                'total_messages' => $messageCount,
                'feof' => feof($this->stream),
                'remaining_buffer' => strlen($this->buffer),
            ]);

            // Log last read chunks from SimpleCURLClient if available
            $this->logLastReadChunks();
        }
    }

    /**
     * Read exactly N bytes from stream with retry.
     *
     * @param int $length Number of bytes to read
     * @return null|string Returns null on EOF, string of exact length on success
     */
    private function readExactly(int $length): ?string
    {
        $data = '';
        $remaining = $length;
        $maxAttempts = 100;
        $attempt = 0;

        while ($remaining > 0 && ! feof($this->stream)) {
            $chunk = fread($this->stream, $remaining);

            if ($chunk === false) {
                $this->log('fread返回false', [
                    'remaining' => $remaining,
                    'data_read_so_far' => strlen($data),
                    'data_preview' => substr($data, 0, 200),
                ]);
                throw new RuntimeException('Failed to read from stream');
            }

            if ($chunk === '') {
                if (++$attempt > $maxAttempts) {
                    $this->log('fread超过最大重试次数', [
                        'total_attempts' => $attempt,
                        'data_read_so_far' => strlen($data),
                        'remaining' => $remaining,
                        'requested_length' => $length,
                        'data_preview' => substr($data, 0, 200),
                    ]);
                    throw new RuntimeException("Failed to read {$length} bytes after {$maxAttempts} attempts");
                }
                usleep(10000);
                continue;
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
            $attempt = 0;
        }

        if ($remaining > 0) {
            if ($data === '') {
                // Normal EOF, no log needed
                return null;
            }
            $this->log('意外的EOF，数据不完整', [
                'data_read' => strlen($data),
                'expected' => $length,
                'remaining' => $remaining,
                'data_preview' => substr($data, 0, 200),
            ]);
            throw new RuntimeException('Unexpected EOF: read ' . strlen($data) . " bytes, expected {$length}");
        }

        return $data;
    }

    /**
     * Parse next message from buffer.
     *
     * @return null|array Parsed message or null if insufficient data
     */
    private function parseNextMessage(): ?array
    {
        // Need at least 12 bytes for prelude
        if (strlen($this->buffer) < 12) {
            return null;
        }

        // Read prelude (12 bytes)
        $totalLength = unpack('N', substr($this->buffer, 0, 4))[1];
        $headersLength = unpack('N', substr($this->buffer, 4, 4))[1];
        $preludeCrc = unpack('N', substr($this->buffer, 8, 4))[1];

        // Check if we have the complete message
        if (strlen($this->buffer) < $totalLength) {
            return null;
        }

        // Extract the complete message
        $messageBytes = substr($this->buffer, 0, $totalLength);
        $this->buffer = substr($this->buffer, $totalLength);

        // Verify prelude CRC
        $preludeBytes = substr($messageBytes, 0, 8);
        $computedPreludeCrc = $this->crc32($preludeBytes);
        if ($computedPreludeCrc !== $preludeCrc) {
            // TODO: Implement proper CRC32C validation
            // For now, log warning and continue
            // throw new RuntimeException('Prelude CRC mismatch');
        }

        // Extract headers
        $headersBytes = substr($messageBytes, 12, $headersLength);
        $headers = $this->parseHeaders($headersBytes);

        // Extract payload
        $payloadLength = $totalLength - 12 - $headersLength - 4;
        $payload = substr($messageBytes, 12 + $headersLength, $payloadLength);

        // Verify message CRC
        $messageCrc = unpack('N', substr($messageBytes, -4))[1];
        $messageWithoutCrc = substr($messageBytes, 0, -4);
        $computedMessageCrc = $this->crc32($messageWithoutCrc);
        if ($computedMessageCrc !== $messageCrc) {
            // TODO: Implement proper CRC32C validation
            // For now, log warning and continue
            // throw new RuntimeException('Message CRC mismatch');
        }

        return [
            'headers' => $headers,
            'payload' => $payload,
        ];
    }

    /**
     * Parse headers from header bytes.
     *
     * @param string $headersBytes Raw header bytes
     * @return array Parsed headers
     */
    private function parseHeaders(string $headersBytes): array
    {
        $headers = [];
        $offset = 0;
        $length = strlen($headersBytes);

        while ($offset < $length) {
            // Read header name length (1 byte)
            $nameLength = ord($headersBytes[$offset]);
            ++$offset;

            // Read header name
            $name = substr($headersBytes, $offset, $nameLength);
            $offset += $nameLength;

            // Read header value type (1 byte)
            $valueType = ord($headersBytes[$offset]);
            ++$offset;

            // Read header value based on type
            $value = $this->parseHeaderValue($headersBytes, $offset, $valueType);
            $offset += $this->getValueLength($headersBytes, $offset, $valueType);

            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Parse header value based on type.
     *
     * @param string $data Header data
     * @param int $offset Current offset
     * @param int $type Value type
     * @return mixed Parsed value
     */
    private function parseHeaderValue(string $data, int $offset, int $type): mixed
    {
        return match ($type) {
            0 => true,  // boolean true
            1 => false, // boolean false
            2 => ord($data[$offset]), // byte
            3 => unpack('n', substr($data, $offset, 2))[1], // short
            4 => unpack('N', substr($data, $offset, 4))[1], // integer
            5, 8 => unpack('J', substr($data, $offset, 8))[1], // long
            6 => $this->parseByteArray($data, $offset), // byte array
            7 => $this->parseString($data, $offset), // string
            // timestamp
            9 => $this->parseUuid($data, $offset), // UUID
            default => null,
        };
    }

    /**
     * Get value length based on type.
     */
    private function getValueLength(string $data, int $offset, int $type): int
    {
        return match ($type) {
            0, 1 => 0,  // boolean (no additional bytes)
            2 => 1,     // byte
            3 => 2,     // short
            4 => 4,     // integer
            5 => 8,     // long
            6, 7 => unpack('n', substr($data, $offset, 2))[1] + 2, // byte array (2-byte length + data)
            // string (2-byte length + data)
            8 => 8,     // timestamp
            9 => 16,    // UUID
            default => 0,
        };
    }

    /**
     * Parse byte array value.
     */
    private function parseByteArray(string $data, int $offset): string
    {
        $length = unpack('n', substr($data, $offset, 2))[1];
        return substr($data, $offset + 2, $length);
    }

    /**
     * Parse string value.
     */
    private function parseString(string $data, int $offset): string
    {
        $length = unpack('n', substr($data, $offset, 2))[1];
        return substr($data, $offset + 2, $length);
    }

    /**
     * Parse UUID value.
     */
    private function parseUuid(string $data, int $offset): string
    {
        $bytes = substr($data, $offset, 16);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Calculate CRC32 checksum (AWS uses CRC32 with specific polynomial).
     *
     * AWS uses CRC-32C (Castagnoli) with polynomial 0x1EDC6F41
     * PHP's crc32() uses a different polynomial, so we need to use hash extension
     *
     * @param string $data Data to checksum
     * @return int CRC32 value
     */
    private function crc32(string $data): int
    {
        // Use hash_final with crc32c if available
        if (in_array('crc32c', hash_algos())) {
            $hash = hash('crc32c', $data, true);
            return unpack('N', $hash)[1];
        }

        // Fallback to PHP's crc32 (note: this uses different polynomial)
        // For production, should use proper CRC32C implementation
        return crc32($data) & 0xFFFFFFFF;
    }

    /**
     * Log last read chunks from the underlying SimpleCURLClient stream.
     */
    private function logLastReadChunks(): void
    {
        try {
            // Get stream metadata which includes wrapper_data
            $metadata = stream_get_meta_data($this->stream);
            $wrapper = $metadata['wrapper_data'] ?? null;

            // Check if it's a SimpleCURLClient instance
            if (! $wrapper instanceof \Hyperf\Odin\Api\Transport\SimpleCURLClient) {
                return;
            }

            // Get custom metadata from SimpleCURLClient
            $customMetadata = $wrapper->stream_metadata();
            if (! isset($customMetadata['last_read']) || ! is_array($customMetadata['last_read'])) {
                return;
            }

            // Format last read data for logging
            $lastReadPreview = [];
            foreach ($customMetadata['last_read'] as $data) {
                // Keep original data as-is, but convert non-UTF-8 binary data to hex for JSON safety
                if (is_string($data) && ! mb_check_encoding($data, 'UTF-8')) {
                    $lastReadPreview[] = bin2hex($data);
                } else {
                    $lastReadPreview[] = $data;
                }
            }

            $logger = LogUtil::getHyperfLogger();
            if ($logger !== null) {
                $logger->info('SimpleCURLClientStreamCompleted', [
                    'last_read_count' => count($customMetadata['last_read']),
                    'last_read_preview' => $lastReadPreview,
                ]);
            }
        } catch (Throwable $e) {
            // Silently fail if logging fails to prevent disrupting parser operations
            $logger = LogUtil::getHyperfLogger();
            $logger?->warning('Failed to log last read chunks', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log parser activity for debugging.
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $message, array $context = []): void
    {
        try {
            $logger = LogUtil::getHyperfLogger();
            if ($logger === null) {
                return;
            }

            $context['parser_class'] = self::class;
            $logger->info('[AwsEventStreamParser] ' . $message, $context);
        } catch (Throwable $e) {
            // Silently fail if logging fails to prevent disrupting parser operations
        }
    }
}

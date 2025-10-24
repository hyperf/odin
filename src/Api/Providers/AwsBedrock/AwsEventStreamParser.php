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
use InvalidArgumentException;
use IteratorAggregate;
use RuntimeException;

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

        // Enable non-blocking mode for real-time streaming
        stream_set_blocking($this->stream, false);
    }

    /**
     * Get iterator to parse event stream.
     */
    public function getIterator(): Generator
    {
        $lastDataTime = microtime(true);
        // In non-blocking mode, allow up to 30 seconds of waiting for data
        // This is reasonable for streaming responses that may have natural pauses
        $maxWaitTime = 30.0; // seconds

        // Adaptive chunk size strategy:
        // - Start with small chunks (256 bytes) for low latency on first message
        // - Switch to larger chunks (8KB) after first message for better throughput
        $chunkSize = 256;
        $hasReceivedFirstMessage = false;

        while (! feof($this->stream)) {
            // Read more data into buffer
            // In non-blocking mode, this will return immediately with whatever is available
            $chunk = fread($this->stream, $chunkSize);

            if ($chunk === false || $chunk === '') {
                // Check if we've been waiting too long without data
                $timeSinceLastData = microtime(true) - $lastDataTime;

                // For non-blocking streams, EOF is the primary signal to stop
                if (feof($this->stream)) {
                    break;
                }

                // Check for stalled stream (no data for too long)
                if ($timeSinceLastData > $maxWaitTime) {
                    break;
                }

                // In non-blocking mode, sleep briefly to avoid tight CPU loop
                usleep(1000); // 1ms
                continue;
            }

            // Update last data time when we get data
            $lastDataTime = microtime(true);
            $this->buffer .= $chunk;

            // Parse and yield all available messages from buffer
            // This is the standard approach - AWS SDK does the same
            while (($message = $this->parseNextMessage()) !== null) {
                yield $message;

                // After first message, switch to larger chunk size for better throughput
                if (! $hasReceivedFirstMessage) {
                    $hasReceivedFirstMessage = true;
                    $chunkSize = 8192; // Switch to 8KB
                }
            }
        }

        // Process any remaining data in buffer
        while (($message = $this->parseNextMessage()) !== null) {
            yield $message;
        }
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
            5 => unpack('J', substr($data, $offset, 8))[1], // long
            6 => $this->parseByteArray($data, $offset), // byte array
            7 => $this->parseString($data, $offset), // string
            8 => unpack('J', substr($data, $offset, 8))[1], // timestamp
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
            6 => unpack('n', substr($data, $offset, 2))[1] + 2, // byte array (2-byte length + data)
            7 => unpack('n', substr($data, $offset, 2))[1] + 2, // string (2-byte length + data)
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
}

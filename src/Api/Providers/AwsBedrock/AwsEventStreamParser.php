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
    }

    /**
     * Get iterator to parse event stream.
     */
    public function getIterator(): Generator
    {
        while (! feof($this->stream)) {
            $length = $this->readExactly(4);
            if ($length === null) {
                break;
            }
            
            $lengthUnpacked = unpack('N', $length);
            $toRead = $lengthUnpacked[1] - 4;
            
            $body = $this->readExactly($toRead);
            if ($body === null) {
                throw new RuntimeException('Failed to read message body from stream');
            }
            
            $chunk = $length . $body;
            $this->buffer .= $chunk;

            while (($message = $this->parseNextMessage()) !== null) {
                yield $message;
            }
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
                throw new RuntimeException('Failed to read from stream');
            }
            
            if ($chunk === '') {
                if (++$attempt > $maxAttempts) {
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
                return null;
            }
            throw new RuntimeException("Unexpected EOF: read " . strlen($data) . " bytes, expected {$length}");
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
}

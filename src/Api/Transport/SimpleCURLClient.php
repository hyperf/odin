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

namespace Hyperf\Odin\Api\Transport;

use CurlHandle;
use Hyperf\Engine\Channel;
use Hyperf\Engine\Coroutine;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use RuntimeException;
use Throwable;

// 注册 stream wrapper
if (! in_array('OdinSimpleCurl', stream_get_wrappers())) {
    stream_wrapper_register('OdinSimpleCurl', SimpleCURLClient::class);
}

class SimpleCURLClient
{
    private const MAX_BUFFER_SIZE = 1024 * 1024; // 1MB

    public $context;

    private CurlHandle $ch;

    private Channel $writeChannel;

    private Channel $headerChannel;

    private string $remaining = '';

    private bool $eof = false;

    private array $options = [];

    private array $responseHeaders = [];

    private bool $closed = false;

    private int $statusCode = 0;

    private ?string $curlError = null;

    private int $curlErrorCode = 0;

    private bool $headersReceived = false;

    public function __construct()
    {
        $this->writeChannel = new Channel(10);
        $this->headerChannel = new Channel(10);
    }

    public function __destruct()
    {
        if (isset($this->ch) && ! $this->closed) {
            curl_close($this->ch);
        }
        $this->stream_close();
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // 解析参数：从 "OdinSimpleCurl://{JSON}" 中提取 JSON
        $optionsStr = substr($path, strlen('OdinSimpleCurl://'));
        $this->options = json_decode($optionsStr, true);

        $this->ch = curl_init($this->options['url']);

        // Build headers array
        $headers = [];
        $hasContentType = false;
        if (isset($this->options['headers']) && is_array($this->options['headers'])) {
            foreach ($this->options['headers'] as $key => $value) {
                $headers[] = $key . ': ' . $value;
                if (strtolower($key) === 'content-type') {
                    $hasContentType = true;
                }
            }
        }

        if (! $hasContentType) {
            $headers[] = 'Content-Type: application/json';
        }

        // Support both pre-encoded body and json array
        // If 'body' is provided (for AWS signature compatibility), use it directly
        // Otherwise, encode the 'json' array
        if (isset($this->options['body'])) {
            $postData = $this->options['body'];
        } elseif (isset($this->options['json'])) {
            $postData = json_encode($this->options['json']);
        } else {
            $postData = '';
        }

        curl_setopt_array($this->ch, [
            CURLOPT_POST => 1,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_BUFFERSIZE => 0,
            CURLOPT_HEADERFUNCTION => [$this, 'headerFunction'],
            CURLOPT_WRITEFUNCTION => [$this, 'writeFunction'],
            CURLOPT_POSTFIELDS => $postData,

            CURLOPT_CONNECTTIMEOUT => $this->options['connect_timeout'] ?? 10,
            CURLOPT_TIMEOUT => 0,  // 流式请求不设置总超时
            CURLOPT_LOW_SPEED_LIMIT => 1,  // 最低速率 1 byte/s
            CURLOPT_LOW_SPEED_TIME => $this->options['read_timeout'] ?? 30,

            CURLOPT_SSL_VERIFYPEER => $this->options['verify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $this->options['verify'] ?? 2,
        ]);

        if (isset($this->options['proxy'])) {
            curl_setopt($this->ch, CURLOPT_PROXY, $this->options['proxy']);
        }

        Coroutine::run(function () {
            $this->eof = false;

            try {
                $result = curl_exec($this->ch);

                // Check for cURL errors
                if ($result === false) {
                    $this->curlError = curl_error($this->ch);
                    $this->curlErrorCode = curl_errno($this->ch);

                    // Send error signal to waiting consumer
                    if (! $this->headersReceived) {
                        $this->headerChannel->push(false);
                    }
                } else {
                    // curl_exec succeeded, but check if we received complete headers
                    // This handles cases where connection succeeds but no HTTP response is received
                    // (e.g., proxy CONNECT succeeded but real request timed out)
                    if (! $this->headersReceived) {
                        $this->curlError = 'No HTTP response received (headers incomplete)';
                        $this->curlErrorCode = 0;
                        $this->headerChannel->push(false);
                    }
                }
                
                $this->writeChannel->push(null);
            } catch (Throwable $e) {
                // Catch any unexpected errors
                $this->curlError = $e->getMessage();
                $this->curlErrorCode = $e->getCode();
                if (! $this->headersReceived) {
                    $this->headerChannel->push(false);
                }
                $this->writeChannel->push(null);
            } finally {
                $this->eof = true;

                if (isset($this->ch)) {
                    curl_close($this->ch);
                    $this->closed = true;
                }
            }
        });

        // Wait for headers to be received (10 seconds timeout)
        $headerReceived = $this->headerChannel->pop(10);

        if ($headerReceived === false) {
            $this->stream_close();
            // Connection failed or timeout
            if ($this->curlError) {
                $curlCode = $this->curlErrorCode;
                $errorMessage = $this->curlError;
                
                // Map cURL error codes to appropriate LLM exceptions
                // 28: Operation timeout
                if ($curlCode === 28) {
                    throw new LLMReadTimeoutException(
                        "Connection timeout: {$errorMessage}",
                        new RuntimeException($errorMessage, $curlCode)
                    );
                }
                
                // For other cURL errors, throw connection timeout exception
                throw new LLMConnectionTimeoutException(
                    "cURL error ({$curlCode}): {$errorMessage}",
                    new RuntimeException($errorMessage, $curlCode)
                );
            }
            
            throw new LLMConnectionTimeoutException(
                'Connection timeout: Failed to receive HTTP headers within 10 seconds',
                new RuntimeException('Failed to receive HTTP headers within timeout'),
                10.0
            );
        }

        return true;
    }

    public function stream_read(int $length): false|string
    {
        // 1. 如果缓冲区有数据，先读取缓冲区
        if ($this->remaining) {
            $ret = substr($this->remaining, 0, $length);
            $this->remaining = substr($this->remaining, $length);
            return $ret;
        }

        // 2. 从 Channel 获取新数据（阻塞等待）
        $data = $this->writeChannel->pop(
            timeout: ($this->options['timeout'] ?? 1) * 1000  // 毫秒
        );

        // 3. 处理超时或 EOF
        if ($data === false) {
            // Channel pop 超时
            return false;
        }

        if ($data === null) {
            // EOF 信号
            $this->eof = true;
            return '';
        }

        // 4. 检查缓冲区溢出
        if (strlen($data) > self::MAX_BUFFER_SIZE) {
            throw new RuntimeException('Buffer overflow: received chunk larger than MAX_BUFFER_SIZE');
        }

        // 5. 读取指定长度的数据
        $ret = substr($data, 0, $length);
        $this->remaining = substr($data, $length);

        return $ret;
    }

    public function stream_eof(): bool
    {
        return $this->eof;
    }

    public function stream_close(): void
    {
        if (isset($this->writeChannel)) {
            $this->writeChannel->close();
        }
        if (isset($this->headerChannel)) {
            $this->headerChannel->close();
        }
    }

    public function writeFunction(CurlHandle $ch, $data): int
    {
        try {
            $result = $this->writeChannel->push($data, timeout: 5);
            if ($result === false) {
                return 0;
            }
            return strlen($data);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function headerFunction(CurlHandle $ch, $header): int
    {
        $len = strlen($header);
        $trimmed = trim($header);

        // Check if this is an empty line (end of headers)
        if (empty($trimmed)) {
            // Headers are complete, get status code and signal ready
            $this->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Only signal header completion if we have a valid HTTP status code
            // Ignore proxy CONNECT responses (status code 0)
            if ($this->statusCode > 0) {
                $this->headersReceived = true;
                $this->headerChannel->push(true);
            } else {
                // This is a proxy CONNECT response, reset headers and wait for real response
                $this->responseHeaders = [];
            }
        } else {
            $headerParts = explode(':', $header, 2);
            if (count($headerParts) === 2) {
                $name = strtolower(trim($headerParts[0]));
                $value = trim($headerParts[1]);
                $this->responseHeaders[$name] = $value;
            }
        }
        return $len;
    }

    public function stream_stat(): array|false
    {
        // Return dummy stat info compatible with fstat()
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 33206,  // 0100666 (regular file, readable/writable)
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 0,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => -1,
            'blocks' => -1,
        ];
    }

    public function stream_metadata(): array
    {
        $metadata = [
            'headers' => $this->responseHeaders,
            'http_code' => $this->statusCode,
        ];

        // Include error information if present
        if ($this->curlError) {
            $metadata['error'] = $this->curlError;
            $metadata['error_code'] = $this->curlErrorCode;
        }

        return $metadata;
    }
}

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
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use Hyperf\Odin\Exception\RuntimeException;
use Hyperf\Odin\Utils\LogUtil;
use Throwable;

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

    private array $lastRead = [];

    public function __construct()
    {
        $this->writeChannel = new Channel(100);
        $this->headerChannel = new Channel(1);
    }

    public function __destruct()
    {
        if (isset($this->ch) && ! $this->closed) {
            curl_close($this->ch);
        }
        $this->stream_close();

        $this->log('SimpleCURLClient::__destruct', [
            'url' => $this->options['url'] ?? 'unknown',
            'eof' => $this->eof,
            'closed' => $this->closed,
        ]);
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

            CURLOPT_CONNECTTIMEOUT => $this->options['connect_timeout'] ?? 30,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_LOW_SPEED_LIMIT => 1,
            CURLOPT_LOW_SPEED_TIME => $this->options['read_timeout'] ?? 60,

            CURLOPT_SSL_VERIFYPEER => $this->options['verify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $this->options['verify'] ?? 2,
        ]);

        if (isset($this->options['proxy'])) {
            curl_setopt($this->ch, CURLOPT_PROXY, $this->options['proxy']);
        }

        Coroutine::run(function () {
            $this->eof = false;
            $this->log('curl_exec协程已启动', [
                'url' => $this->options['url'],
            ]);

            try {
                $startTime = microtime(true);
                $result = curl_exec($this->ch);
                $elapsed = microtime(true) - $startTime;

                $this->log('curl_exec执行完成', [
                    'result' => $result === false ? 'false' : 'true',
                    'elapsed' => $elapsed,
                ]);

                // Check for cURL errors
                if ($result === false) {
                    $this->curlError = curl_error($this->ch);
                    $this->curlErrorCode = curl_errno($this->ch);

                    $this->log('curl_exec执行失败', [
                        'error' => $this->curlError,
                        'error_code' => $this->curlErrorCode,
                        'elapsed' => $elapsed,
                    ]);

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
                        $this->log('curl_exec成功但响应头不完整', [
                            'elapsed' => $elapsed,
                        ]);
                        $this->headerChannel->push(false);
                    } else {
                        $this->log('curl_exec成功且响应头完整', [
                            'elapsed' => $elapsed,
                            'status_code' => $this->statusCode,
                        ]);
                    }
                }

                $this->log('向Channel发送EOF信号', []);
                $this->writeChannel->push(null);
            } catch (Throwable $e) {
                // Catch any unexpected errors
                $this->curlError = $e->getMessage();
                $this->curlErrorCode = $e->getCode();
                $this->log('curl_exec协程异常', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                ]);
                if (! $this->headersReceived) {
                    $this->headerChannel->push(false);
                }
                $this->writeChannel->push(null);
            } finally {
                $this->eof = true;
                $this->log('curl_exec协程结束，设置EOF标志', [
                    'eof' => $this->eof,
                ]);

                if (isset($this->ch)) {
                    curl_close($this->ch);
                    $this->closed = true;
                }
            }
        });

        $headerTimeout = $this->options['header_timeout'] ?? 60;
        $headerReceived = $this->headerChannel->pop($headerTimeout);

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
                "Connection timeout: Failed to receive HTTP headers within {$headerTimeout} seconds",
                new RuntimeException('Failed to receive HTTP headers within timeout'),
                (float) $headerTimeout
            );
        }

        return true;
    }

    public function stream_read(int $length): false|string
    {
        if ($this->remaining) {
            $ret = substr($this->remaining, 0, $length);
            $this->remaining = substr($this->remaining, $length);
            $this->recordLastRead($ret);
            return $ret;
        }

        $readTimeout = $this->options['read_timeout'] ?? 60;
        $startTime = microtime(true);
        $data = $this->writeChannel->pop(timeout: $readTimeout);
        $elapsed = microtime(true) - $startTime;

        // 3. 处理超时或 EOF
        if ($data === false) {
            // Channel pop 超时
            $this->log('Channel读取超时', [
                'requested_length' => $length,
                'timeout' => $readTimeout,
                'elapsed' => $elapsed,
                'eof' => $this->eof,
                'remaining_buffer' => substr($this->remaining, 0, 200),
            ]);
            $this->recordLastRead(false);
            return false;
        }

        if ($data === null) {
            // EOF signal
            $this->eof = true;
            $this->log('收到EOF信号，流正常结束', [
                'elapsed' => $elapsed,
            ]);

            $this->recordLastRead('');
            return '';
        }

        $dataLength = strlen($data);

        // 4. 检查缓冲区溢出
        if ($dataLength > self::MAX_BUFFER_SIZE) {
            $this->log('缓冲区溢出', [
                'received_length' => $dataLength,
                'max_buffer_size' => self::MAX_BUFFER_SIZE,
                'data_preview' => substr($data, 0, 500),
            ]);
            throw new LLMNetworkException('Buffer overflow: received chunk larger than MAX_BUFFER_SIZE');
        }

        // 5. 读取指定长度的数据
        $ret = substr($data, 0, $length);
        $this->remaining = substr($data, $length);

        $this->recordLastRead($ret);
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
        $dataLength = strlen($data);

        try {
            $result = $this->writeChannel->push($data, timeout: 60);

            if ($result === false) {
                $this->curlError = 'Channel push timeout: consumer not reading data';
                $this->curlErrorCode = CURLE_WRITE_ERROR;
                $this->log('推送数据到Channel超时', [
                    'data_length' => $dataLength,
                    'data_preview' => substr($data, 0, 200),
                ]);
                return 0;
            }

            return $dataLength;
        } catch (Throwable $e) {
            $this->curlError = 'Channel push error: ' . $e->getMessage();
            $this->curlErrorCode = CURLE_WRITE_ERROR;
            $this->log('推送数据到Channel异常', [
                'data_length' => $dataLength,
                'data_preview' => substr($data, 0, 200),
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
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
            'last_read' => $this->lastRead,
        ];

        if ($this->curlError) {
            $metadata['error'] = $this->curlError;
            $metadata['error_code'] = $this->curlErrorCode;
        }

        return $metadata;
    }

    /**
     * Record last read data, keeping only the last 5 chunks.
     *
     * @param bool|string $data The data that was read
     */
    private function recordLastRead(bool|string $data): void
    {
        $this->lastRead[] = $data;
        // Keep only last 5 chunks
        if (count($this->lastRead) > 5) {
            array_shift($this->lastRead);
        }
    }

    /**
     * Format last read data for logging.
     *
     * @return array Formatted preview of last read chunks
     */
    private function formatLastReadForLog(): array
    {
        $preview = [];
        foreach ($this->lastRead as $data) {
            // Keep original data as-is, but convert non-UTF-8 binary data to hex for JSON safety
            if (is_string($data) && !mb_check_encoding($data, 'UTF-8')) {
                $preview[] = bin2hex($data);
            } else {
                $preview[] = $data;
            }
        }
        return $preview;
    }

    /**
     * Log stream activity for debugging.
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $message, array $context = []): void
    {
        try {
            $logger = LogUtil::getHyperfLogger();
            $context['coroutine_id'] = Coroutine::id();
            
            if ($logger === null) {
                // Fallback to error_log if logger is not available (e.g., during shutdown)
                error_log(sprintf(
                    '[SimpleCURLClient] %s %s',
                    $message,
                    json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ));
                return;
            }

            $logger->info('[SimpleCURLClient] ' . $message, $context);
        } catch (Throwable $e) {
            // Last resort: output to error_log
            error_log(sprintf(
                '[SimpleCURLClient] Failed to log: %s (original message: %s)',
                $e->getMessage(),
                $message
            ));
        }
    }
}

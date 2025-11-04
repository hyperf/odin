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
    private const MAX_BUFFER_SIZE = 1024 * 1024;

    public $context;

    private CurlHandle $ch;

    private Channel $writeChannel;

    private Channel $headerChannel;

    private string $remaining = '';

    private bool $eof = false;

    private array $options = [];

    private array $responseHeaders = [];

    private int $statusCode = 0;

    private ?string $curlError = null;

    private int $curlErrorCode = 0;

    private bool $headersReceived = false;

    public function __construct()
    {
        $this->writeChannel = new Channel(100);
        $this->headerChannel = new Channel(1);
    }

    public function __destruct()
    {
        $this->stream_close();
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $optionsStr = substr($path, strlen('OdinSimpleCurl://'));
        $this->options = json_decode($optionsStr, true);

        $this->ch = curl_init($this->options['url']);

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
            CURLOPT_LOW_SPEED_TIME => $this->options['stream_chunk'] ?? 120,

            CURLOPT_SSL_VERIFYPEER => $this->options['verify'] ?? true,
            CURLOPT_SSL_VERIFYHOST => $this->options['verify'] ?? 2,
        ]);

        if (isset($this->options['proxy'])) {
            curl_setopt($this->ch, CURLOPT_PROXY, $this->options['proxy']);
        }

        Coroutine::run(function () {
            try {
                $startTime = microtime(true);
                $result = curl_exec($this->ch);
                $elapsed = microtime(true) - $startTime;

                if ($result === false) {
                    $this->curlError = curl_error($this->ch);
                    $this->curlErrorCode = curl_errno($this->ch);

                    $this->log('curl_exec执行失败', [
                        'error' => $this->curlError,
                        'error_code' => $this->curlErrorCode,
                        'elapsed' => $elapsed,
                    ]);

                    if (! $this->headersReceived) {
                        $this->headerChannel->push(false);
                    }
                } else {
                    if (! $this->headersReceived) {
                        $this->curlError = 'No HTTP response received (headers incomplete)';
                        $this->curlErrorCode = 0;
                        $this->log('curl_exec成功但响应头不完整', [
                            'elapsed' => $elapsed,
                        ]);
                        $this->headerChannel->push(false);
                    }
                }

                $this->writeChannel->push(null);
            } catch (Throwable $e) {
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
                if (isset($this->ch)) {
                    curl_close($this->ch);
                }
            }
        });

        $headerTimeout = $this->options['header_timeout'] ?? 60;
        $headerReceived = $this->headerChannel->pop($headerTimeout);

        if ($headerReceived === false) {
            $this->stream_close();
            if ($this->curlError) {
                $curlCode = $this->curlErrorCode;
                $errorMessage = $this->curlError;

                if ($curlCode === 28) {
                    throw new LLMReadTimeoutException(
                        "Connection timeout: {$errorMessage}",
                        new RuntimeException($errorMessage, $curlCode)
                    );
                }

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
            return $ret;
        }

        $chunkTimeout = $this->options['stream_chunk'] ?? 120;
        $startTime = microtime(true);
        $data = $this->writeChannel->pop(timeout: $chunkTimeout);
        $elapsed = microtime(true) - $startTime;

        if ($data === false) {
            $this->log('Channel读取超时', [
                'requested_length' => $length,
                'timeout' => $chunkTimeout,
                'elapsed' => $elapsed,
                'eof' => $this->eof,
                'remaining_buffer' => substr($this->remaining, 0, 200),
            ]);
            return false;
        }

        if ($data === null) {
            $this->eof = true;
            return '';
        }

        $dataLength = strlen($data);

        if ($dataLength > self::MAX_BUFFER_SIZE) {
            $this->log('缓冲区溢出', [
                'received_length' => $dataLength,
                'max_buffer_size' => self::MAX_BUFFER_SIZE,
                'data_preview' => substr($data, 0, 500),
            ]);
            throw new LLMNetworkException('Buffer overflow: received chunk larger than MAX_BUFFER_SIZE');
        }

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

        if (empty($trimmed)) {
            $this->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($this->statusCode > 0) {
                $this->headersReceived = true;
                $this->headerChannel->push(true);
            } else {
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
        return [
            'dev' => 0,
            'ino' => 0,
            'mode' => 33206,
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

        if ($this->curlError) {
            $metadata['error'] = $this->curlError;
            $metadata['error_code'] = $this->curlErrorCode;
        }

        return $metadata;
    }

    private function log(string $message, array $context = []): void
    {
        $logger = LogUtil::getHyperfLogger();
        if (! $logger) {
            return;
        }

        $context['coroutine_id'] = Coroutine::id();
        $logger->info('[SimpleCURLClient] ' . $message, $context);
    }
}

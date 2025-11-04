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

use Generator;
use Hyperf\Odin\Exception\InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

class SSEClient implements IteratorAggregate
{
    private const EOL = "\n";

    private const EVENT_END = "\n\n";

    private const BUFFER_SIZE = 8192;

    private const DEFAULT_RETRY = 3000; // 默认重试时间，单位毫秒

    private ?int $timeout = null;

    private ?float $connectionStartTime = null;

    private int $retryTimeout = self::DEFAULT_RETRY;

    private ?string $lastEventId = null;

    /**
     * 流式异常检测器.
     */
    private ?StreamExceptionDetector $exceptionDetector = null;

    /**
     * 日志记录器.
     */
    private ?LoggerInterface $logger = null;

    /**
     * Flag to indicate if stream should be closed early.
     */
    private bool $shouldClose = false;

    /**
     * @param resource $stream
     */
    public function __construct(
        private $stream,
        private bool $autoClose = true,
        ?array $timeoutConfig = null,
        ?LoggerInterface $logger = null
    ) {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('Stream must be a resource');
        }

        // 从timeoutConfig中提取stream_total作为基础超时
        $this->timeout = isset($timeoutConfig['stream_total']) ? (int) $timeoutConfig['stream_total'] : null;
        $this->connectionStartTime = microtime(true);
        $this->logger = $logger;

        // 如果提供了超时配置，初始化流异常检测器
        if ($timeoutConfig !== null) {
            $this->exceptionDetector = new StreamExceptionDetector($timeoutConfig, $logger);
            $this->logger?->debug('Stream exception detector initialized', [
                'timeout_config' => $timeoutConfig,
            ]);
        }
    }

    /**
     * 确保流资源在对象销毁时被释放.
     */
    public function __destruct()
    {
        if ($this->autoClose && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function getIterator(): Generator
    {
        try {
            $lastCheckTime = microtime(true);
            $chunkCounter = 0;

            $this->logger?->info('[SSEClient] 开始SSE流处理', [
                'feof' => feof($this->stream),
                'is_resource' => is_resource($this->stream),
            ]);

            while (! feof($this->stream) && ! $this->shouldClose) {
                // 定期检查超时状态，每1秒检查一次
                $now = microtime(true);
                if ($now - $lastCheckTime > 1.0) {
                    $lastCheckTime = $now;

                    // 使用专业的超时检测器
                    $this->exceptionDetector?->checkTimeout();
                }

                $chunk = stream_get_line($this->stream, self::BUFFER_SIZE, self::EVENT_END);

                if ($chunk === false) {
                    // 使用专业的超时检测器
                    $this->exceptionDetector?->checkTimeout();

                    continue;
                }

                ++$chunkCounter;

                $eventData = $this->parseEvent($chunk);
                $event = SSEEvent::fromArray($eventData);

                if ($event->getId() !== null) {
                    $this->lastEventId = $event->getId();
                }

                if ($event->getRetry() !== null) {
                    $retryInt = (int) $event->getRetry();
                    // 设置合理的上下限，避免极端值
                    if ($retryInt > 0 && $retryInt <= 600000) { // 最大10分钟
                        $this->retryTimeout = $retryInt;
                    }
                }

                // 如果是注释或空行，则跳过
                if ($event->isEmpty()) {
                    continue;
                }

                // 通知流异常检测器已接收到块，传递调试信息
                $chunkInfo = [
                    'event_type' => $event->getEvent(),
                    'event_id' => $event->getId(),
                    'data_preview' => is_string($event->getData())
                        ? substr($event->getData(), 0, 200)
                        : (is_array($event->getData()) ? json_encode($event->getData()) : 'non-string-data'),
                    'raw_chunk_size' => strlen($chunk),
                ];
                $this->exceptionDetector?->onChunkReceived($chunkInfo);

                yield $event;

                // check stream status after yielding the current chunk
                if (! is_resource($this->stream) || feof($this->stream)) {
                    $this->logger?->info('[SSEClient] 流无效或已EOF，退出循环', [
                        'total_chunks' => $chunkCounter,
                        'is_resource' => is_resource($this->stream),
                        'feof' => feof($this->stream),
                    ]);
                    break;
                }
            }
        } finally {
            $this->logger?->info('[SSEClient] SSE流处理完成', [
                'total_chunks' => $chunkCounter,
                'resource' => is_resource($this->stream),
                'feof' => feof($this->stream),
                'should_close' => $this->shouldClose,
            ]);

            if (is_resource($this->stream)) {
                $this->logLastReadChunks($this->stream);
            }

            if ($this->autoClose && is_resource($this->stream)) {
                $this->logger?->info('[SSEClient] 关闭流资源');
                fclose($this->stream);
            }
        }
    }

    /**
     * 获取最后一个事件 ID.
     */
    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    /**
     * 获取重试超时时间（毫秒）.
     */
    public function getRetryTimeout(): int
    {
        return $this->retryTimeout;
    }

    /**
     * Signal the SSE client to close the stream early.
     * This is useful when a [DONE] event is received to prevent waiting for more data.
     */
    public function closeEarly(): void
    {
        $this->shouldClose = true;
        $this->logger?->debug('SSE stream marked for early closure');
    }

    /**
     * 解析 SSE 事件.
     *
     * SSE 格式规范：
     * - event: 事件类型
     * - data: 事件数据
     * - id: 事件 ID
     * - retry: 重连等待时间
     */
    protected function parseEvent(string $chunk): array
    {
        $result = [
            'event' => 'message',
            'data' => '',
            'id' => null,
            'retry' => null,
        ];

        // 移除 UTF-8 BOM
        $chunk = preg_replace('/^\xEF\xBB\xBF/', '', $chunk);

        // 按行分割
        $lines = preg_split('/' . self::EOL . '/', $chunk);

        foreach ($lines as $line) {
            // 忽略注释和空行
            if (empty($line) || str_starts_with($line, ':')) {
                continue;
            }

            // 解析字段
            if (str_contains($line, ':')) {
                [$field, $value] = explode(':', $line, 2);
                $value = ltrim($value, ' ');

                switch ($field) {
                    case 'event':
                        $result['event'] = $value;
                        break;
                    case 'data':
                        $result['data'] = $result['data'] ? $result['data'] . "\n" . $value : $value;
                        break;
                    case 'id':
                        $result['id'] = $value;
                        break;
                    case 'retry':
                        if (is_numeric($value)) {
                            $retry = (int) $value;
                            if ($retry > 0) {  // 只接受正整数
                                $result['retry'] = $retry;
                            }
                        }
                        break;
                }
            } else {
                // 如果行中没有冒号，则视为字段名，值为空
                if ($line === 'data') {
                    $result['data'] = $result['data'] ? $result['data'] . "\n" : '';
                }
            }
        }

        // 尝试解析 JSON 数据
        if (! empty($result['data'])) {
            // 特殊处理 [DONE] 标记，这通常表示流结束
            if ($result['data'] === '[DONE]') {
                $result['event'] = 'done';
            } else {
                try {
                    $jsonData = json_decode($result['data'], true, 512, JSON_THROW_ON_ERROR);
                    $result['data'] = $jsonData;
                } catch (JsonException $e) {
                    // 保持原始字符串数据，不进行转换
                    // 可以选择记录错误，但不影响处理流程
                    $this->logger?->debug('Failed to parse JSON data in SSE event', [
                        'error' => $e->getMessage(),
                        'data' => $result['data'],
                    ]);
                }
            }
        }

        return $result;
    }

    /**
     * Log last read chunks from the underlying SimpleCURLClient stream.
     *
     * @param resource $stream Stream resource
     */
    private function logLastReadChunks($stream): void
    {
        try {
            // Get stream metadata which includes wrapper_data
            $metadata = stream_get_meta_data($stream);
            $wrapper = $metadata['wrapper_data'] ?? null;

            // Check if it's a SimpleCURLClient instance
            if (! $wrapper instanceof SimpleCURLClient) {
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

            $this->logger?->info('SimpleCURLClientStreamCompleted', [
                'last_read_count' => count($customMetadata['last_read']),
                'last_read_preview' => $lastReadPreview,
            ]);
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to log last read chunks', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查连接是否超时.
     */
    private function isTimedOut(): bool
    {
        if ($this->timeout === null || $this->connectionStartTime === null) {
            return false;
        }

        return (microtime(true) - $this->connectionStartTime) > $this->timeout;
    }
}

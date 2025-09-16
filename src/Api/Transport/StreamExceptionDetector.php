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

use Hyperf\Odin\Exception\LLMException\Network\LLMStreamTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMThinkingStreamTimeoutException;
use Psr\Log\LoggerInterface;

/**
 * 流式响应异常检测器.
 */
class StreamExceptionDetector
{
    /**
     * 初始化时间戳.
     */
    private float $startTime;

    /**
     * 上一个块接收时间戳.
     */
    private float $lastChunkTime;

    /**
     * 是否已接收第一个块.
     */
    private bool $firstChunkReceived = false;

    /**
     * 超时配置.
     */
    private array $timeoutConfig;

    /**
     * 日志记录器.
     */
    private ?LoggerInterface $logger;

    /**
     * 最后接收到的块信息.
     */
    private ?array $lastChunkInfo = null;

    /**
     * 已接收的总块数.
     */
    private int $totalChunksReceived = 0;

    /**
     * 构造函数.
     */
    public function __construct(array $timeoutConfig, ?LoggerInterface $logger = null)
    {
        $this->startTime = microtime(true);
        $this->lastChunkTime = $this->startTime;
        $this->timeoutConfig = $this->normalizeTimeoutConfig($timeoutConfig);
        $this->logger = $logger;
    }

    /**
     * 检测超时情况.
     *
     * @throws LLMStreamTimeoutException 流式响应超时
     * @throws LLMThinkingStreamTimeoutException 思考阶段超时
     */
    public function checkTimeout(): void
    {
        $now = microtime(true);
        $elapsedTotal = $now - $this->startTime;

        // 检查总体超时
        if ($elapsedTotal > $this->timeoutConfig['total']) {
            // 准备详细的调试信息
            $debugInfo = [
                'elapsed' => $elapsedTotal,
                'timeout' => $this->timeoutConfig['total'],
                'total_chunks_received' => $this->totalChunksReceived,
                'time_since_last_chunk' => $this->firstChunkReceived ? $now - $this->lastChunkTime : null,
                'last_chunk_info' => $this->lastChunkInfo,
            ];

            $this->logger?->warning('检测到流式响应总体超时', $debugInfo);

            // 构建简洁的异常消息（详细信息已记录在日志中）
            $message = sprintf('流式响应总体超时，已经等待 %.2f 秒', $elapsedTotal);

            throw new LLMStreamTimeoutException(
                $message,
                null,
                'total',
                $elapsedTotal
            );
        }

        // 如果尚未收到第一个块，检查思考超时
        if (! $this->firstChunkReceived) {
            if ($elapsedTotal > $this->timeoutConfig['stream_first']) {
                // 准备详细的调试信息
                $debugInfo = [
                    'elapsed' => $elapsedTotal,
                    'timeout' => $this->timeoutConfig['stream_first'],
                    'total_chunks_received' => $this->totalChunksReceived,
                    'waiting_for_first_chunk' => true,
                ];

                $this->logger?->warning('检测到等待首个流式响应块超时', $debugInfo);

                // 构建简洁的异常消息（详细信息已记录在日志中）
                $message = sprintf('等待首个流式响应块超时，已经等待 %.2f 秒', $elapsedTotal);

                throw new LLMThinkingStreamTimeoutException(
                    $message,
                    null,
                    $elapsedTotal
                );
            }
        } else {
            // 如果已收到第一个块，检查块间超时
            $elapsedSinceLastChunk = $now - $this->lastChunkTime;
            if ($elapsedSinceLastChunk > $this->timeoutConfig['stream_chunk']) {
                // 准备详细的调试信息
                $debugInfo = [
                    'elapsed_since_last' => $elapsedSinceLastChunk,
                    'timeout' => $this->timeoutConfig['stream_chunk'],
                    'total_chunks_received' => $this->totalChunksReceived,
                    'total_elapsed_time' => $now - $this->startTime,
                    'last_chunk_info' => $this->lastChunkInfo,
                ];

                $this->logger?->warning('检测到流式响应块间隔超时', $debugInfo);

                // 构建简洁的异常消息（详细信息已记录在日志中）
                $message = sprintf('流式响应块间超时，已经等待 %.2f 秒', $elapsedSinceLastChunk);

                throw new LLMStreamTimeoutException(
                    $message,
                    null,
                    'chunk_interval',
                    $elapsedSinceLastChunk
                );
            }
        }
    }

    /**
     * 接收到块后调用此方法更新时间戳.
     */
    public function onChunkReceived(array $chunkInfo = []): void
    {
        $this->lastChunkTime = microtime(true);
        ++$this->totalChunksReceived;

        // 记录最后接收到的块信息（用于调试）
        $this->lastChunkInfo = [
            'chunk_number' => $this->totalChunksReceived,
            'timestamp' => $this->lastChunkTime,
            'time_since_start' => $this->lastChunkTime - $this->startTime,
            'chunk_data' => $chunkInfo,
        ];

        if (! $this->firstChunkReceived) {
            $this->firstChunkReceived = true;
            $initialResponseTime = $this->lastChunkTime - $this->startTime;
            $this->logger?->debug('接收到首个流式响应块', [
                'initial_response_time' => $initialResponseTime,
                'chunk_info' => $chunkInfo,
            ]);
        }
    }

    /**
     * 规范化超时配置，设置默认值.
     */
    private function normalizeTimeoutConfig(array $config): array
    {
        return [
            'total' => $config['stream_total'] ?? $config['total'] ?? 600.0,
            'stream_first' => $config['stream_first'] ?? 60.0,
            'stream_chunk' => $config['stream_chunk'] ?? 30.0,
        ];
    }
}

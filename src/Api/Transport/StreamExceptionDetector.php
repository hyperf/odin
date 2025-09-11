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
            $this->logger?->warning('Stream total timeout detected', [
                'elapsed' => $elapsedTotal,
                'timeout' => $this->timeoutConfig['total'],
            ]);
            throw new LLMStreamTimeoutException(
                sprintf('流式响应总体超时，已经等待 %.2f 秒', $elapsedTotal),
                null,
                'total',
                $elapsedTotal
            );
        }

        // 如果尚未收到第一个块，检查思考超时
        if (! $this->firstChunkReceived) {
            if ($elapsedTotal > $this->timeoutConfig['stream_first']) {
                $this->logger?->warning('Stream first chunk timeout detected', [
                    'elapsed' => $elapsedTotal,
                    'timeout' => $this->timeoutConfig['stream_first'],
                ]);
                throw new LLMThinkingStreamTimeoutException(
                    sprintf('等待首个流式响应块超时，已经等待 %.2f 秒', $elapsedTotal),
                    null,
                    $elapsedTotal
                );
            }
        } else {
            // 如果已收到第一个块，检查块间超时
            $elapsedSinceLastChunk = $now - $this->lastChunkTime;
            if ($elapsedSinceLastChunk > $this->timeoutConfig['stream_chunk']) {
                $this->logger?->warning('Stream chunk interval timeout detected', [
                    'elapsed_since_last' => $elapsedSinceLastChunk,
                    'timeout' => $this->timeoutConfig['stream_chunk'],
                ]);
                throw new LLMStreamTimeoutException(
                    sprintf('流式响应块间超时，已经等待 %.2f 秒', $elapsedSinceLastChunk),
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
    public function onChunkReceived(): void
    {
        $this->lastChunkTime = microtime(true);
        if (! $this->firstChunkReceived) {
            $this->firstChunkReceived = true;
            $initialResponseTime = $this->lastChunkTime - $this->startTime;
            $this->logger?->debug('First chunk received', [
                'initial_response_time' => $initialResponseTime,
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

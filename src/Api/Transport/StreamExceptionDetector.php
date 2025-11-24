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

class StreamExceptionDetector
{
    private float $startTime;

    private float $lastChunkTime;

    private bool $firstChunkReceived = false;

    private array $timeoutConfig;

    private ?LoggerInterface $logger;

    private ?array $lastChunkInfo = null;

    private int $totalChunksReceived = 0;

    public function __construct(array $timeoutConfig, ?LoggerInterface $logger = null)
    {
        $this->startTime = microtime(true);
        $this->lastChunkTime = $this->startTime;
        $this->timeoutConfig = $this->normalizeTimeoutConfig($timeoutConfig);
        $this->logger = $logger;
    }

    public function checkTimeout(): void
    {
        $now = microtime(true);
        $elapsedTotal = $now - $this->startTime;

        if ($elapsedTotal > $this->timeoutConfig['total']) {
            $debugInfo = [
                'elapsed' => $elapsedTotal,
                'timeout' => $this->timeoutConfig['total'],
                'total_chunks_received' => $this->totalChunksReceived,
                'time_since_last_chunk' => $this->firstChunkReceived ? $now - $this->lastChunkTime : null,
                'last_chunk_info' => $this->lastChunkInfo,
            ];

            $this->logger?->warning('检测到流式响应总体超时', $debugInfo);

            $message = sprintf('流式响应总体超时，已经等待 %.2f 秒', $elapsedTotal);

            throw new LLMStreamTimeoutException(
                $message,
                null,
                'total',
                $elapsedTotal
            );
        }

        if (! $this->firstChunkReceived) {
            if ($elapsedTotal > $this->timeoutConfig['stream_first']) {
                $debugInfo = [
                    'elapsed' => $elapsedTotal,
                    'timeout' => $this->timeoutConfig['stream_first'],
                    'total_chunks_received' => $this->totalChunksReceived,
                    'waiting_for_first_chunk' => true,
                ];

                $this->logger?->warning('检测到等待首个流式响应块超时', $debugInfo);

                $message = sprintf('等待首个流式响应块超时，已经等待 %.2f 秒', $elapsedTotal);

                throw new LLMThinkingStreamTimeoutException(
                    $message,
                    null,
                    $elapsedTotal
                );
            }
        } else {
            $elapsedSinceLastChunk = $now - $this->lastChunkTime;
            if ($elapsedSinceLastChunk > $this->timeoutConfig['stream_chunk']) {
                $debugInfo = [
                    'elapsed_since_last' => $elapsedSinceLastChunk,
                    'timeout' => $this->timeoutConfig['stream_chunk'],
                    'total_chunks_received' => $this->totalChunksReceived,
                    'total_elapsed_time' => $now - $this->startTime,
                    'last_chunk_info' => $this->lastChunkInfo,
                ];

                $this->logger?->warning('检测到流式响应块间隔超时', $debugInfo);

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

    public function onChunkReceived(array $chunkInfo = []): void
    {
        $this->lastChunkTime = microtime(true);
        ++$this->totalChunksReceived;

        $this->lastChunkInfo = [
            'chunk_number' => $this->totalChunksReceived,
            'timestamp' => $this->lastChunkTime,
            'time_since_start' => $this->lastChunkTime - $this->startTime,
            'chunk_data' => $chunkInfo,
        ];

        if (! $this->firstChunkReceived) {
            $this->firstChunkReceived = true;
        }
    }

    private function normalizeTimeoutConfig(array $config): array
    {
        return [
            'total' => $config['stream_total'] ?? $config['total'] ?? 600.0,
            'stream_first' => $config['stream_first'] ?? 60.0,
            'stream_chunk' => $config['stream_chunk'] ?? 30.0,
        ];
    }
}

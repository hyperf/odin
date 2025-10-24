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
use IteratorAggregate;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Custom Converse Stream Converter.
 *
 * Converts AWS Bedrock Converse API streaming responses to OpenAI-compatible format
 * WITHOUT depending on AWS SDK.
 */
class CustomConverseStreamConverter implements IteratorAggregate
{
    protected ?LoggerInterface $logger;

    private AwsEventStreamParser $parser;

    private ?string $messageId = null;

    private string $model = '';

    /**
     * Constructor.
     *
     * @param ResponseInterface $response Guzzle HTTP response with event stream body
     * @param null|LoggerInterface $logger Logger instance
     * @param string $model Model ID
     */
    public function __construct(ResponseInterface $response, ?LoggerInterface $logger = null, string $model = '')
    {
        // Detach the stream resource from the StreamInterface wrapper
        // This allows direct access to the underlying resource for non-blocking I/O
        $stream = $response->getBody()->detach();
        if (! is_resource($stream)) {
            throw new RuntimeException('Failed to detach stream resource from response body');
        }

        $this->parser = new AwsEventStreamParser($stream);
        $this->messageId = $response->getHeaderLine('x-amzn-requestid') ?: uniqid('bedrock-');
        $this->model = $model;
        $this->logger = $logger;
    }

    /**
     * Get iterator to process stream events.
     */
    public function getIterator(): Generator
    {
        $created = time();
        $isFirstChunk = true;
        $toolCallIndex = 0;
        $chunkIndex = 0;
        $firstChunks = [];
        $lastChunks = [];
        $maxChunksToLog = 5;

        try {
            foreach ($this->parser as $message) {
                if (empty($message) || ! isset($message['payload'])) {
                    continue;
                }

                // Parse JSON payload
                $chunk = json_decode($message['payload'], true);
                if (empty($chunk) || ! is_array($chunk)) {
                    continue;
                }

                $timestamp = microtime(true);
                $chunkWithTime = [
                    'index' => $chunkIndex,
                    'timestamp' => $timestamp,
                    'datetime' => date('Y-m-d H:i:s', (int) $timestamp) . '.' . substr((string) fmod($timestamp, 1), 2, 6),
                    'data' => $chunk,
                ];

                // Collect first 5 chunks
                if ($chunkIndex < $maxChunksToLog) {
                    $firstChunks[] = $chunkWithTime;
                }

                // Keep last 5 chunks
                if (count($lastChunks) >= $maxChunksToLog) {
                    array_shift($lastChunks);
                }
                $lastChunks[] = $chunkWithTime;

                ++$chunkIndex;

                // Convert to OpenAI format
                $openAiChunk = $this->convertChunkToOpenAiFormat($chunk, $created, $isFirstChunk, $toolCallIndex);

                if ($openAiChunk !== null) {
                    $isFirstChunk = false;
                    // Yield raw data without SSE format (ChatCompletionStreamResponse will handle SSE formatting)
                    yield $openAiChunk;
                }
            }

            // Send [DONE] signal
            yield '[DONE]';
        } finally {
            // Log streaming summary (always executed, even if generator is terminated early)
            $this->logger?->info('AwsBedrockConverseCustomStreamSummary', [
                'message_id' => $this->messageId,
                'model' => $this->model,
                'total_chunks' => $chunkIndex,
                'first_chunks' => $firstChunks,
                'last_chunks' => $lastChunks,
            ]);
        }
    }

    /**
     * Convert AWS Bedrock chunk to OpenAI format.
     *
     * @param array $chunk AWS Bedrock event chunk
     * @param int $created Timestamp
     * @param bool $isFirstChunk Whether this is the first chunk
     * @param int $toolCallIndex Tool call index counter
     * @return null|array OpenAI formatted chunk or null if should skip
     */
    private function convertChunkToOpenAiFormat(array $chunk, int $created, bool $isFirstChunk, int &$toolCallIndex): ?array
    {
        $openAiChunk = [
            'id' => $this->messageId,
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model,
            'choices' => [],
        ];

        $delta = [];
        $finishReason = null;

        // Handle different event types based on the actual chunk structure
        // AWS Bedrock sends event type in headers, and the payload contains the data directly
        if (isset($chunk['role'])) {
            // Message start event: {"role":"assistant", "p":"..."}
            $delta['role'] = 'assistant';
            $finishReason = null;
        } elseif (isset($chunk['start'])) {
            // Content block start: {"start":{"toolUse":{...}}, "contentBlockIndex":0, "p":"..."}
            if (isset($chunk['start']['toolUse'])) {
                // Tool use start
                $toolUse = $chunk['start']['toolUse'];
                $delta['tool_calls'] = [[
                    'index' => $toolCallIndex,
                    'id' => $toolUse['toolUseId'] ?? uniqid('call_'),
                    'type' => 'function',
                    'function' => [
                        'name' => $toolUse['name'] ?? '',
                        'arguments' => '',
                    ],
                ]];
                ++$toolCallIndex;
            }
        } elseif (isset($chunk['delta'], $chunk['contentBlockIndex'])) {
            // Content delta: {"contentBlockIndex":0, "delta":{"text":"..."}, "p":"..."}
            if (isset($chunk['delta']['text'])) {
                // Text delta
                $delta['content'] = $chunk['delta']['text'];
            } elseif (isset($chunk['delta']['toolUse'])) {
                // Tool use input delta
                $toolUse = $chunk['delta']['toolUse'];
                $delta['tool_calls'] = [[
                    'index' => $toolCallIndex - 1,
                    'function' => [
                        'arguments' => $toolUse['input'] ?? '',
                    ],
                ]];
            }
        } elseif (isset($chunk['contentBlockIndex']) && ! isset($chunk['delta'])) {
            // Content block stop: {"contentBlockIndex":0, "p":"..."}
            return null;
        } elseif (isset($chunk['stopReason'])) {
            // Message stop: {"stopReason":"end_turn", "p":"..."}
            $stopReason = $chunk['stopReason'] ?? 'stop';
            $finishReason = match ($stopReason) {
                'end_turn' => 'stop',
                'tool_use' => 'tool_calls',
                'max_tokens' => 'length',
                'stop_sequence' => 'stop',
                default => $stopReason,
            };
        } elseif (isset($chunk['usage'])) {
            // Metadata event with usage: {"metrics":{...}, "usage":{...}, "p":"..."}
            // Match the usage processing in ResponseHandler::convertConverseToPsrResponse
            $usage = $chunk['usage'];
            $inputTokens = $usage['inputTokens'] ?? 0;
            $cacheReadTokens = $usage['cacheReadInputTokens'] ?? 0;
            $cacheWriteTokens = $usage['cacheWriteInputTokens'] ?? 0;

            // 按照 OpenAI 的方式：promptTokens = 总处理的提示tokens（包括缓存）
            $promptTokens = $inputTokens + $cacheReadTokens + $cacheWriteTokens;
            $completionTokens = $usage['outputTokens'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;

            $openAiChunk['usage'] = [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'prompt_tokens_details' => [
                    'cache_write_input_tokens' => $cacheWriteTokens,
                    'cache_read_input_tokens' => $cacheReadTokens,
                    // 兼容 OpenAI 格式：cached_tokens表示缓存命中
                    'audio_tokens' => 0,
                    'cached_tokens' => $cacheReadTokens,
                ],
                'completion_tokens_details' => [
                    'reasoning_tokens' => 0,
                ],
            ];
            // Return the chunk with usage information
            $openAiChunk['choices'][] = [
                'index' => 0,
                'delta' => [],
                'finish_reason' => null,
            ];
            return $openAiChunk;
        } elseif (isset($chunk['metrics'])) {
            // Metadata without usage - skip
            return null;
        }

        // Build choice
        $choice = [
            'index' => 0,
            'delta' => $delta,
        ];

        if ($finishReason !== null) {
            $choice['finish_reason'] = $finishReason;
        } else {
            $choice['finish_reason'] = null;
        }

        $openAiChunk['choices'][] = $choice;

        return $openAiChunk;
    }
}

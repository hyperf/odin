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

use Aws\Api\Parser\EventParsingIterator;
use Aws\Result;
use Generator;
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use IteratorAggregate;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * AWS Bedrock 格式转换器.
 *
 * 此类用于将 AWS Bedrock Claude API 的流式响应事件转换为与 OpenAI 兼容的格式
 * 实现迭代器接口，可以直接在 foreach 中使用
 */
class AwsBedrockConverseFormatConverter implements IteratorAggregate
{
    /**
     * 日志记录器.
     */
    protected ?LoggerInterface $logger;

    /**
     * AWS 流式响应对象
     *
     * @var mixed
     */
    private EventParsingIterator $responseStream;

    /**
     * 消息 ID.
     */
    private ?string $messageId = null;

    /**
     * 模型 ID.
     */
    private string $model = '';

    /**
     * 构造函数.
     *
     * @param mixed $result AWS Bedrock SDK 返回的响应结果对象
     * @param null|LoggerInterface $logger 日志记录器
     */
    public function __construct(Result $result, ?LoggerInterface $logger = null, string $model = '')
    {
        $body = $result->get('stream');
        if (! $body instanceof EventParsingIterator) {
            throw new LLMModelException('Invalid response stream');
        }
        $this->responseStream = $body;
        $this->messageId = $result->get('@messageId')['headers']['x-amzn-requestid'] ?? uniqid('bedrock-');

        $this->model = $model;
        $this->logger = $logger;
    }

    /**
     * 实现 IteratorAggregate 接口的 getIterator 方法
     * 处理流式响应并转换为 OpenAI 兼容格式.
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

        foreach ($this->responseStream as $chunk) {
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

            // Keep a rolling window of last 5 chunks
            $lastChunks[] = $chunkWithTime;
            if (count($lastChunks) > $maxChunksToLog) {
                array_shift($lastChunks);
            }

            ++$chunkIndex;

            foreach ($chunk as $eventType => $event) {
                // 根据事件类型处理
                switch ($eventType) {
                    // 1. 处理消息开始事件
                    case 'messageStart':
                        if ($isFirstChunk) {
                            yield $this->formatMessageStartEvent($created);
                            $isFirstChunk = false;
                        }
                        break;
                        // 2. 处理内容块开始事件
                    case 'contentBlockStart':
                        // 处理工具使用类型的内容块
                        if (isset($event['start']['toolUse'])) {
                            $toolId = $event['start']['toolUse']['toolUseId'] ?? ('tool-' . uniqid());
                            $toolName = $event['start']['toolUse']['name'] ?? '';
                            ++$toolCallIndex;
                            yield $this->formatToolCallStartEvent($created, $toolCallIndex, $toolId, $toolName);
                        }
                        break;
                        // 3. 处理内容更新事件
                    case 'contentBlockDelta':
                        // 处理文本增量更新
                        $textDelta = $event['delta']['text'] ?? '';
                        if (! empty($textDelta)) {
                            yield $this->formatTextDeltaEvent($created, $textDelta);
                        }
                        $thinking = $event['delta']['thinking'] ?? '';
                        if (! empty($thinking)) {
                            yield $this->formatThinkingDeltaEvent($created, $thinking);
                        }

                        // 处理工具调用参数的JSON增量
                        if (isset($event['delta']['toolUse'])) {
                            $partialJson = $event['delta']['toolUse']['input'] ?? '';
                            // 特殊处理第一个空片段
                            if ($partialJson === '') {
                                continue 2;
                            }

                            yield $this->formatToolJsonDeltaEvent($created, $toolCallIndex - 1, $partialJson);
                        }
                        break;
                    case 'contentBlockStop':
                    case 'metadata':
                        if (isset($event['usage'])) {
                            yield $this->formatUsageEvent($created, $event['usage']);
                        }
                        break;
                    case 'messageStop':
                        yield $this->formatMessageStopEvent($created, $event['stopReason'] ?? 'stop');
                        break;
                    default:
                        $this->log(LogLevel::DEBUG, "未处理的事件类型: {$eventType}", $event);
                        break;
                }
            }
        }

        // Log first 5 and last 5 chunks after all processing
        if (! empty($firstChunks)) {
            $this->log(LogLevel::INFO, 'FirstChunks', [
                'total_chunks' => $chunkIndex,
                'chunks' => $firstChunks,
            ]);
        }

        if (! empty($lastChunks)) {
            $this->log(LogLevel::INFO, 'LastChunks', [
                'total_chunks' => $chunkIndex,
                'chunks' => $lastChunks,
            ]);
        }
    }

    /**
     * 获取消息 ID.
     */
    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * 获取模型 ID.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    private function formatUsageEvent(int $created, array $usage): string
    {
        // 转换Claude的token统计方式为Qwen格式（与非流式保持一致）
        // Claude: inputTokens=新输入, cacheReadInputTokens=缓存命中
        // OpenAI: promptTokens=总输入(包括缓存), cachedTokens=缓存命中
        $inputTokens = $usage['inputTokens'] ?? 0;
        $cacheReadTokens = $usage['cacheReadInputTokens'] ?? 0;
        $cacheWriteTokens = $usage['cacheWriteInputTokens'] ?? 0;

        // 按照 OpenAI 的方式：promptTokens = 总处理的提示tokens（包括缓存）
        $promptTokens = $inputTokens + $cacheReadTokens + $cacheWriteTokens;
        $completionTokens = $usage['outputTokens'] ?? 0;
        $totalTokens = $promptTokens + $completionTokens;

        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'aws.bedrock',
            'choices' => null,
            'usage' => [
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
            ],
        ]);
    }

    /**
     * 格式化消息开始事件.
     *
     * @param int $created 创建时间戳
     * @return string 格式化后的事件JSON
     */
    private function formatMessageStartEvent(int $created): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'anthropic.claude-3-sonnet-20240229-v1:0',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'role' => 'assistant',
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);
    }

    /**
     * 格式化工具调用开始事件.
     *
     * @param int $created 创建时间戳
     * @param int $toolIndex 工具索引
     * @param string $toolId 工具ID
     * @param string $toolName 工具名称
     * @return string 格式化后的事件JSON
     */
    private function formatToolCallStartEvent(int $created, int $toolIndex, string $toolId, string $toolName): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'anthropic.claude-3-sonnet-20240229-v1:0',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'tool_calls' => [
                            [
                                'index' => $toolIndex - 1,
                                'id' => $toolId,
                                'type' => 'function',
                                'function' => [
                                    'name' => $toolName,
                                    'arguments' => '',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);
    }

    /**
     * 格式化文本增量更新事件.
     *
     * @param int $created 创建时间戳
     * @param string $textDelta 文本增量内容
     * @return string 格式化后的事件JSON
     */
    private function formatTextDeltaEvent(int $created, string $textDelta): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'aws.bedrock',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'content' => $textDelta,
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);
    }

    private function formatThinkingDeltaEvent(int $created, string $thinking): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'aws.bedrock',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'reasoning_content' => $thinking,
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);
    }

    /**
     * 格式化工具JSON增量更新事件.
     *
     * @param int $created 创建时间戳
     * @param int $toolIndex 工具索引
     * @param string $partialJson 部分JSON内容
     * @return string 格式化后的事件JSON
     */
    private function formatToolJsonDeltaEvent(int $created, int $toolIndex, string $partialJson): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'anthropic.claude-3-sonnet-20240229-v1:0',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'tool_calls' => [
                            [
                                'index' => $toolIndex,
                                'function' => [
                                    'arguments' => $partialJson,
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);
    }

    /**
     * 格式化消息结束事件.
     *
     * @param int $created 创建时间戳
     * @return string 格式化后的事件JSON
     */
    private function formatMessageStopEvent(int $created, string $reason = 'stop'): string
    {
        return $this->formatOpenAiEvent([
            'id' => $this->messageId ?? ('bedrock-' . uniqid()),
            'object' => 'chat.completion.chunk',
            'created' => $created,
            'model' => $this->model ?: 'anthropic.claude-3-sonnet-20240229-v1:0',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [],
                    'finish_reason' => $reason,
                ],
            ],
        ]);
    }

    /**
     * 格式化为 OpenAI 兼容的事件.
     *
     * @param array $data 事件数据
     * @return string JSON 格式的事件数据
     */
    private function formatOpenAiEvent(array $data): string
    {
        return json_encode($data);
    }

    /**
     * 记录日志.
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->log($level, '[AWS Bedrock] ' . $message, $context);
    }
}

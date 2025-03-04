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
class AwsBedrockFormatConverter implements IteratorAggregate
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
    public function __construct(Result $result, ?LoggerInterface $logger = null)
    {
        $body = $result->get('body');
        if (! $body instanceof EventParsingIterator) {
            throw new LLMModelException('Invalid response stream');
        }
        $this->responseStream = $result->get('body');

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

        foreach ($this->responseStream as $chunk) {
            $event = $this->parseChunk($chunk);

            if (empty($event)) {
                continue;
            }

            // 更新元数据
            if (isset($event['message_id']) && $this->messageId === null) {
                $this->messageId = $event['message_id'];
            } elseif (isset($event['message']['id']) && $this->messageId === null) {
                $this->messageId = $event['message']['id'];
            }

            if (isset($event['model']) && empty($this->model)) {
                $this->model = $event['model'];
            } elseif (isset($event['message']['model']) && empty($this->model)) {
                $this->model = $event['message']['model'];
            }

            $eventType = $event['type'] ?? 'unknown';

            // 根据事件类型处理
            switch ($eventType) {
                // 1. 处理消息开始事件
                case 'message_start':
                    if ($isFirstChunk) {
                        yield $this->formatMessageStartEvent($created);
                        $isFirstChunk = false;
                    }
                    break;
                    // 2. 处理内容块开始事件
                case 'content_block_start':
                    // 处理工具使用类型的内容块
                    if (isset($event['content_block']['type']) && $event['content_block']['type'] === 'tool_use') {
                        $toolId = $event['content_block']['id'] ?? ('tool-' . uniqid());
                        $toolName = $event['content_block']['name'] ?? '';
                        ++$toolCallIndex;

                        yield $this->formatToolCallStartEvent($created, $toolCallIndex, $toolId, $toolName);
                    }
                    break;
                    // 3. 处理内容更新事件
                case 'content_block_delta':
                    // 处理文本增量更新
                    if (isset($event['delta']['type']) && $event['delta']['type'] === 'text_delta') {
                        $textDelta = $event['delta']['text'] ?? '';
                        if (! empty($textDelta)) {
                            yield $this->formatTextDeltaEvent($created, $textDelta);
                        }
                    }

                    // 处理工具调用参数的JSON增量
                    if (isset($event['delta']['type']) && $event['delta']['type'] === 'input_json_delta'
                        && isset($event['delta']['partial_json'])) {
                        $partialJson = $event['delta']['partial_json'];
                        // 特殊处理第一个空片段
                        if ($partialJson === '') {
                            continue 2;
                        }

                        yield $this->formatToolJsonDeltaEvent($created, $toolCallIndex - 1, $partialJson);
                    }
                    break;
                    // 4. 处理内容块结束事件
                case 'content_block_stop':
                    // 目前不需要特殊处理
                    break;
                    // 5. 处理消息增量事件
                case 'message_delta':
                    // 目前不需要特殊处理
                    break;
                    // 6. 处理消息结束事件
                case 'message_stop':
                    yield $this->formatMessageStopEvent($created);
                    break;
                default:
                    $this->log(LogLevel::DEBUG, "未处理的事件类型: {$eventType}", $event);
                    break;
            }
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
    private function formatMessageStopEvent(int $created): string
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
                    'finish_reason' => 'stop',
                ],
            ],
        ]);
    }

    /**
     * 解析 AWS Bedrock 数据块.
     *
     * @param mixed $chunk AWS Bedrock 响应块
     * @return null|array|bool 解析后的事件数据，失败返回 null
     */
    private function parseChunk(array $chunk): null|array|bool
    {
        $rawData = $chunk['chunk']['bytes'] ?? null;
        if (! is_string($rawData) || empty($rawData)) {
            $this->log(LogLevel::WARNING, '无效的数据块');
            return null;
        }

        return json_decode($rawData, true);
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

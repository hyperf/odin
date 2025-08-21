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

namespace Hyperf\Odin\Api\Response;

use Generator;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Api\Transport\SSEEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Utils\EventUtil;
use IteratorAggregate;
use JsonException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

class ChatCompletionStreamResponse extends AbstractResponse implements Stringable
{
    protected ?string $id = null;

    protected ?string $object = null;

    protected ?int $created = null;

    protected ?string $model = null;

    /**
     * @var array<ChatCompletionChoice>
     */
    protected array $choices = [];

    /**
     * 兼容多种类型的迭代器.
     */
    protected ?SSEClient $sseClient = null;

    /**
     * 支持 IteratorAggregate 接口的迭代器.
     */
    protected ?IteratorAggregate $iterator = null;

    protected AfterChatCompletionsStreamEvent $afterChatCompletionsStreamEvent;

    /**
     * 构造函数.
     *
     * @param null|PsrResponseInterface $response HTTP 响应对象
     * @param null|LoggerInterface $logger 日志记录器
     * @param null|IteratorAggregate|SSEClient $streamIterator 流式迭代器，可以是 SSEClient 或 IteratorAggregate
     */
    public function __construct(?PsrResponseInterface $response = null, ?LoggerInterface $logger = null, $streamIterator = null)
    {
        // 根据类型初始化不同的迭代器
        if ($streamIterator instanceof SSEClient) {
            $this->sseClient = $streamIterator;
        } elseif ($streamIterator instanceof IteratorAggregate) {
            $this->iterator = $streamIterator;
        }

        if ($response === null) {
            if (! $this->sseClient && ! $this->iterator) {
                throw new LLMException('Stream iterator is required');
            }
            $response = new Response(200);
        }

        parent::__construct($response, $logger);
    }

    public function __toString(): string
    {
        return 'Stream Response';
    }

    public function getStreamIterator(): Generator
    {
        // 优先使用 IteratorAggregate
        if ($this->iterator) {
            return $this->iterateWithCustomIterator();
        }

        // 其次使用 SSEClient
        if ($this->sseClient) {
            return $this->iterateWithSSEClient();
        }

        // 最后使用传统方式
        return $this->iterateWithLegacyMethod();
    }

    public function setAfterChatCompletionsStreamEvent(AfterChatCompletionsStreamEvent $afterChatCompletionsStreamEvent): void
    {
        $this->afterChatCompletionsStreamEvent = $afterChatCompletionsStreamEvent;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(?string $object): self
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(null|int|string $created): self
    {
        $this->created = (int) $created;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    public function setChoices(array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    protected function parseContent(): self
    {
        return $this;
    }

    /**
     * 使用自定义迭代器（IteratorAggregate）处理流数据.
     */
    private function iterateWithCustomIterator(): Generator
    {
        try {
            $startTime = microtime(true);
            foreach ($this->iterator->getIterator() as $data) {
                // 处理结束标记
                if ($data === '[DONE]' || $data === json_encode('[DONE]')) {
                    $this->logger?->debug('Stream completed');
                    break;
                }

                // 解析 JSON 数据（如果数据是字符串）
                if (is_string($data)) {
                    try {
                        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $this->logger?->warning('Invalid JSON in stream', ['data' => $data, 'error' => $e->getMessage()]);
                        continue;
                    }
                }

                // 确保数据是有效的数组
                if (! is_array($data)) {
                    $this->logger?->warning('Invalid data format', ['data' => $data]);
                    continue;
                }

                // 更新响应元数据
                $this->updateMetadata($data);

                // 生成ChatCompletionChoice对象
                yield from $this->yieldChoices($data['choices'] ?? []);
            }

            // Set duration and create completion response
            $this->handleStreamCompletion($startTime);
        } catch (Throwable $e) {
            $this->logger?->error('Error processing custom iterator', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // 重新抛出异常，让调用方可以处理
        }
    }

    /**
     * 使用SSEClient处理流数据.
     */
    private function iterateWithSSEClient(): Generator
    {
        try {
            $startTime = microtime(true);
            /** @var SSEEvent $event */
            foreach ($this->sseClient->getIterator() as $event) {
                $data = $event->getData();

                // 处理结束标记
                if ($data === '[DONE]') {
                    $this->logger?->debug('SSE stream completed');
                    break;
                }

                // 只处理数据事件
                if ($event->getEvent() !== 'message') {
                    $this->logger?->debug('Skipping non-message event', ['event' => $event->getEvent()]);
                    continue;
                }

                // 确保数据是有效的数组
                if (! is_array($data)) {
                    $this->logger?->warning('Invalid data format', ['data' => $data]);
                    continue;
                }

                // 更新响应元数据
                $this->updateMetadata($data);

                // 生成ChatCompletionChoice对象
                yield from $this->yieldChoices($data['choices'] ?? []);
            }

            // Set duration and create completion response
            $this->handleStreamCompletion($startTime);
        } catch (Throwable $e) {
            $this->logger?->error('Error processing SSE stream', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // 重新抛出异常，让调用方可以处理
        }
    }

    /**
     * 更新响应元数据.
     */
    private function updateMetadata(array $data): void
    {
        $this->setId($data['id'] ?? null);
        $this->setObject($data['object'] ?? null);
        $this->setCreated($data['created'] ?? null);
        $this->setModel($data['model'] ?? null);
        if (! empty($data['usage'])) {
            $usage = $data['usage'];
            // 检测并转换DashScope格式的字段
            if ($this->isDashScopeUsage($usage)) {
                $usage = $this->convertDashScopeUsage($usage);
            }
            $this->setUsage(Usage::fromArray($usage));
        }
    }

    /**
     * 检测是否为DashScope格式的usage数据
     */
    private function isDashScopeUsage(array $usage): bool
    {
        return isset($usage['prompt_tokens_details']['cache_creation_input_tokens']) 
            || isset($usage['prompt_tokens_details']['cache_type']) 
            || isset($usage['prompt_tokens_details']['cache_creation']);
    }

    /**
     * 转换DashScope格式的usage数据为标准格式
     */
    private function convertDashScopeUsage(array $usage): array
    {
        if (isset($usage['prompt_tokens_details'])) {
            $promptTokensDetails = $usage['prompt_tokens_details'];
            
            // 1. 优先转换外层的 cache_creation_input_tokens -> cache_write_input_tokens
            if (isset($promptTokensDetails['cache_creation_input_tokens'])) {
                $usage['prompt_tokens_details']['cache_write_input_tokens'] = $promptTokensDetails['cache_creation_input_tokens'];
            }
            // 2. 如果外层没有，再尝试从内层 cache_creation 获取
            elseif (isset($promptTokensDetails['cache_creation']['ephemeral_5m_input_tokens'])) {
                $usage['prompt_tokens_details']['cache_write_input_tokens'] = $promptTokensDetails['cache_creation']['ephemeral_5m_input_tokens'];
            }
        }
        
        return $usage;
    }

    /**
     * 生成选择对象
     */
    private function yieldChoices(array $choices): Generator
    {
        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                $this->logger?->warning('Invalid choice format', ['choice' => $choice]);
                continue;
            }
            $chatCompletionChoice = ChatCompletionChoice::fromArray($choice);
            $this->choices[] = $chatCompletionChoice;
            yield $chatCompletionChoice;
        }
    }

    /**
     * 使用传统方式处理流数据（后备方法）.
     */
    private function iterateWithLegacyMethod(): Generator
    {
        // 保留原有的实现作为后备
        $startTime = microtime(true);
        $body = $this->originResponse->getBody();

        $buffer = '';
        while (! $body->eof()) {
            $chunk = $body->read(4096);
            if (! $chunk) {
                break;
            }

            $buffer .= $chunk;
            // 处理接收到的数据块
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // 保留不完整的最后一行

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $line = substr($line, 5);
                }

                if (trim($line) === '[DONE]') {
                    break 2;
                }

                try {
                    $data = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
                    $this->updateMetadata($data);
                    yield from $this->yieldChoices($data['choices'] ?? []);
                } catch (JsonException $e) {
                    $this->logger?->warning('InvalidJsonResponse', ['line' => $line, 'error' => $e->getMessage()]);
                    continue;
                }
            }
        }

        // Set duration and create completion response
        $this->handleStreamCompletion($startTime);
    }

    /**
     * Handle stream completion - create response and dispatch event.
     */
    private function handleStreamCompletion(float $startTime): void
    {
        if (! isset($this->afterChatCompletionsStreamEvent)) {
            return;
        }

        // Set duration and create completion response
        $this->afterChatCompletionsStreamEvent->setDuration(microtime(true) - $startTime);

        // Create and set the completed ChatCompletionResponse
        $completionResponse = $this->createChatCompletionResponse();
        $this->afterChatCompletionsStreamEvent->setCompletionResponse($completionResponse);

        EventUtil::dispatch($this->afterChatCompletionsStreamEvent);
    }

    private function createChatCompletionResponse(): ChatCompletionResponse
    {
        // Create a merged choices array by combining content from the same index
        $mergedChoices = [];

        foreach ($this->choices as $choice) {
            $index = $choice->getIndex() ?? 0;

            if (! isset($mergedChoices[$index])) {
                // Initialize new choice with basic info
                $mergedChoices[$index] = [
                    'index' => $index,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                        'reasoning_content' => null,
                        'tool_calls' => [],
                    ],
                    'logprobs' => $choice->getLogprobs(),
                    'finish_reason' => null,
                ];
            }

            // Merge content
            $message = $choice->getMessage();
            // Append content
            $content = $message->getContent();
            if (! empty($content)) {
                $mergedChoices[$index]['message']['content'] .= $content;
            }

            // Handle reasoning content for AssistantMessage
            if ($message instanceof AssistantMessage) {
                $reasoningContent = $message->getReasoningContent();
                if (! empty($reasoningContent)) {
                    if ($mergedChoices[$index]['message']['reasoning_content'] === null) {
                        $mergedChoices[$index]['message']['reasoning_content'] = '';
                    }
                    $mergedChoices[$index]['message']['reasoning_content'] .= $reasoningContent;
                }

                // Merge tool calls
                $toolCalls = $message->getToolCalls();
                if (! empty($toolCalls)) {
                    foreach ($toolCalls as $toolCall) {
                        $toolCallId = $toolCall->getId();
                        $existingToolCallFound = false;

                        // Check if this tool call already exists and merge stream arguments
                        foreach ($mergedChoices[$index]['message']['tool_calls'] as &$existingToolCall) {
                            if ($existingToolCall['id'] === $toolCallId) {
                                // Append stream arguments for existing tool call
                                if (isset($existingToolCall['function']['arguments'])) {
                                    $existingToolCall['function']['arguments'] .= $toolCall->getStreamArguments();
                                } else {
                                    $existingToolCall['function']['arguments'] = $toolCall->getStreamArguments();
                                }
                                $existingToolCallFound = true;
                                break;
                            }
                        }

                        // Add new tool call if not found
                        if (! $existingToolCallFound) {
                            $mergedChoices[$index]['message']['tool_calls'][] = [
                                'id' => $toolCall->getId(),
                                'type' => $toolCall->getType(),
                                'function' => [
                                    'name' => $toolCall->getName(),
                                    'arguments' => $toolCall->getStreamArguments() ?: json_encode($toolCall->getArguments()),
                                ],
                            ];
                        }
                    }
                }
            }

            // Update finish reason if provided
            if ($choice->getFinishReason()) {
                $mergedChoices[$index]['finish_reason'] = $choice->getFinishReason();
            }
        }

        // Clean up empty reasoning_content
        foreach ($mergedChoices as &$choice) {
            if (empty($choice['message']['reasoning_content'])) {
                $choice['message']['reasoning_content'] = null;
            }
            if (empty($choice['message']['tool_calls'])) {
                unset($choice['message']['tool_calls']);
            }
        }

        // Sort choices by index
        ksort($mergedChoices);
        $mergedChoices = array_values($mergedChoices);

        // Create response content similar to regular chat completion response
        $responseContent = [
            'id' => $this->getId(),
            'object' => $this->getObject() ?: 'chat.completion',
            'created' => $this->getCreated(),
            'model' => $this->getModel(),
            'choices' => $mergedChoices,
        ];

        // Add usage if available
        if ($this->getUsage()) {
            $responseContent['usage'] = $this->getUsage()->toArray();
        }

        // Create a mock response with the merged content
        $jsonContent = json_encode($responseContent);
        $mockResponse = new Response(200, ['Content-Type' => 'application/json'], $jsonContent);

        // Create and return ChatCompletionResponse
        return new ChatCompletionResponse($mockResponse, $this->logger);
    }
}

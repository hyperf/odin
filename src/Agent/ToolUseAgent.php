<?php

namespace Hyperf\Odin\Agent;

use Hyperf\Odin\Api\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\ModelInterface;
use Hyperf\Odin\ModelMapper;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use function json_encode;
use const JSON_UNESCAPED_UNICODE;

class ToolUseAgent
{
    protected ModelInterface $model;

    protected array $messages = [];

    protected ?LoggerInterface $logger;

    protected int $maxMessagesLimit = 50; // 最大消息数量限制

    protected array $tools = [];

    public function __construct(ModelMapper $modelMapper, ?LoggerInterface $logger = null)
    {
        $this->model = $modelMapper->getDefaultModel();
        $logger && $this->logger = $logger;
    }

    public function setTools(array $tools): static
    {
        $this->validateTools($tools);
        $this->tools = $tools;
        return $this;
    }

    public function chat(array|string|MessageInterface $messages, float $temperature = 0.2): ChatCompletionResponse
    {
        $this->handleMessages($messages);

        // Trim messages to avoid overflow
        $this->trimMessages();

        try {
            chat_call:
            $response = $this->model->chat(messages: $this->messages, temperature: $temperature, tools: $this->tools);
            $this->logger?->debug('FinishedReason: ' . $response->getFirstChoice()->getFinishReason());
            // 判断 finish_reason 是否为 length
            length_check:
            if ($response->getFirstChoice()->getFinishReason() === 'length') {
                $messageContent = $response->getFirstChoice()->getMessage()->getContent();

                // 重新调用 llm 续写剩余内容
                $newResponse = $this->model->chat(messages: array_merge($this->messages, [new AssistantMessage($messageContent), new UserMessage('Continue')]), temperature: $temperature, tools: $this->tools);
                $newMessageContent = $newResponse->getFirstChoice()->getMessage()->getContent();

                // 拼接续写后的内容
                $finalContent = $messageContent . $newMessageContent;
                $newResponse->getFirstChoice()->getMessage()->setContent($finalContent);
                $response = $newResponse;
                goto length_check;
            }

            if ($response->getFirstChoice()->getMessage() instanceof AssistantMessage) {
                $this->messages[] = $response->getFirstChoice()->getMessage();
            }

            // Log the response for each step
            $message = $response->getFirstChoice()->getMessage();
            if ($message->getContent()) {
                $this->logger?->debug('AI Message: ' . $response);
            }

            // Check if the response indicates a tool call
            if ($response->getFirstChoice()->isFinishedByToolCall()) {
                // Call the appropriate tool
                $results = $this->callTool($response, $this->tools);
                foreach ($results as $callId => $result) {
                    // Build Tool Message
                    $this->messages[] = new ToolMessage($result, $callId);
                }
                // 检查是否所有的 ToolCall 都有对应 CallID 的 ToolMessage
                $toolCalls = $response->getFirstChoice()->getMessage()->getToolCalls();
                $toolCallIds = array_map(fn($toolCall) => $toolCall->getId(), $toolCalls);
                $toolMessageIds = [];
                foreach ($this->messages as $message) {
                    if ($message instanceof ToolMessage) {
                        $toolMessageIds[] = $message->getToolCallId();
                    }
                }
                $missingToolCallIds = array_diff($toolCallIds, $toolMessageIds);
                if (!empty($missingToolCallIds)) {
                    // 构造空的 ToolMessage 加到 $this->messages 中
                    foreach ($missingToolCallIds as $missingToolCallId) {
                        $this->messages[] = new ToolMessage('No Result.', $missingToolCallId);
                    }
                }
                // Trim messages after tool call
                $this->trimMessages();
                goto chat_call;
            }
        } catch (\Exception $e) {
            $errorMessage = is_array($e->getMessage()) ? json_encode($e->getMessage()) : $e->getMessage();
            $this->logger?->error('Error during chat: ' . $errorMessage);
            throw new \RuntimeException('Error during chat: ' . $errorMessage, previous: $e);
        }
        return $response;
    }

    protected function callTool(ChatCompletionResponse $response, array $tools): array
    {
        $message = $response->getFirstChoice()->getMessage();
        if (! $message instanceof AssistantMessage) {
            return [];
        }
        $result = [];
        $toolCalls = $message->getToolCalls();
        foreach ($toolCalls as $toolCall) {
            // Find the tool that matches the tool call
            foreach ($tools as $tool) {
                if ($tool instanceof ToolDefinition) {
                    if ($tool->getName() === $toolCall->getName()) {
                        // Execute the tool
                        $callToolResult = call_user_func($tool->getToolHandler(), $toolCall->getArguments());
                        $result[$toolCall->getId()] = $callToolResult;

                        // Log the tool call result
                        $this->logger?->debug(sprintf('Tool %s calling with arguments: %s', $tool->getName(), json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE)));
                    }
                }
            }
        }
        return $result;
    }

    public function handleMessages(array|string|MessageInterface $messages): array
    {
        if (is_string($messages)) {
            $messages = new UserMessage($messages);
            $this->messages[] = $messages;
        } elseif ($messages instanceof MessageInterface) {
            $this->messages[] = $messages;
        } elseif (is_array($messages)) {
            foreach ($messages as $message) {
                if (! $message instanceof MessageInterface) {
                    throw new InvalidArgumentException('The message must be an instance of MessageInterface.');
                }
            }
            $this->messages = array_merge($this->messages, $messages);
        }
        return $this->messages;
    }

    protected function validateTools(array $tools): void
    {
        foreach ($tools as $tool) {
            if (! $tool instanceof ToolDefinition) {
                throw new InvalidArgumentException('The tool must be an instance of ToolDefinition.');
            }
        }
    }

    public function trimMessages(): void
    {
        if (count($this->messages) > $this->maxMessagesLimit) {
            $firstUserMessage = null;
            foreach ($this->messages as $index => $message) {
                if ($message instanceof UserMessage) {
                    $firstUserMessage = $message;
                    break;
                }
            }

            $deleteMessages = [];
            foreach ($this->messages as $index => $message) {
                if ($message instanceof AssistantMessage) {
                    $toolCalls = $message->getToolCalls();
                    if (!empty($toolCalls)) {
                        for ($i = $index + 1; $i < count($this->messages); $i++) {
                            $nextMessage = $this->messages[$i];
                            if ($nextMessage instanceof ToolMessage) {
                                foreach ($toolCalls as $toolCall) {
                                    if ($nextMessage->getToolCallId() === $toolCall->getId()) {
                                        $deleteMessages[] = $nextMessage;
                                        break;
                                    }
                                }
                            }
                        }
                        $deleteMessages[] = $message;
                    }
                }
            }

            foreach ($deleteMessages as $deleteMessage) {
                $key = array_search($deleteMessage, $this->messages);
                if ($key !== false) {
                    unset($this->messages[$key]);
                }
            }

            $this->messages = array_values($this->messages);

            $this->messages = array_slice($this->messages, -$this->getMaxMessagesLimit() + 1);

            if ($firstUserMessage) {
                array_unshift($this->messages, $firstUserMessage);
            }
        }
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getMaxMessagesLimit(): int
    {
        return $this->maxMessagesLimit;
    }

    public function setMaxMessagesLimit(int $maxMessagesLimit): static
    {
        $this->maxMessagesLimit = $maxMessagesLimit;
        return $this;
    }
}

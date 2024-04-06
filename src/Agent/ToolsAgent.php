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

namespace Hyperf\Odin\Agent;

use Hyperf\Odin\Apis\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionChoice;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Apis\OpenAI\Response\ToolCall;
use Hyperf\Odin\Memory\MemoryInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\FunctionMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Model\ModelInterface;
use Hyperf\Odin\Model\SkylarkModel;
use Hyperf\Odin\Observer;
use Hyperf\Odin\Prompt\PromptInterface;
use Hyperf\Odin\Tools\ToolInterface;
use InvalidArgumentException;

class ToolsAgent
{
    protected bool $debug = false;

    protected int $currentIteration = 0;

    protected $messageBuffer = [];

    public function __construct(
        public ModelInterface $model,
        public PromptInterface $prompt,
        public MemoryInterface $memory,
        public ?Observer $observer,
        public array $tools = [],
        public int $maxIterations = 10,
    ) {
    }

    public function invoke(array $inputs, string $conversationId)
    {
        $currentStageUserMessage = $this->prompt->getUserPrompt($inputs['input'] ?? '');
        $this->memory->setSystemMessage($this->prompt->getSystemPrompt(), $conversationId);
        $conversationMessages = $this->memory->getConversations($conversationId);
        $response = $this->chat(array_merge($conversationMessages, [$currentStageUserMessage]), conversationId: $conversationId, tools: $this->tools);
        // Handle tool calls
        if ($response->getChoices()) {
            foreach ($response->getChoices() as $choice) {
                if (! $choice instanceof ChatCompletionChoice || ! $choice->getMessage() instanceof AssistantMessage || ! $choice->getMessage()
                        ->hasToolCalls()) {
                    continue;
                }
                $toolCalls = $choice->getMessage()->getToolCalls();
                if ($toolCalls) {
                    $toolCallsResults = [];
                    $toolsWithKey = [];
                    foreach ($this->tools as $tool) {
                        if ($tool instanceof ToolInterface) {
                            $toolDefinition = $tool->toToolDefinition();
                            $toolsWithKey[$toolDefinition->getName()] = $toolDefinition;
                        } elseif ($tool instanceof ToolDefinition) {
                            $toolsWithKey[$tool->getName()] = $tool;
                        }
                    }
                    foreach ($toolCalls as $toolCall) {
                        if (! $toolCall instanceof ToolCall) {
                            continue;
                        }
                        $targetTool = $toolsWithKey[$toolCall->getName()] ?? null;
                        if (! $targetTool) {
                            continue;
                        }
                        $toolHandler = $targetTool->getToolHandler();
                        if (is_callable($toolHandler)) {
                            $this->observer?->info(sprintf('Invoking tool %s with arguments %s', $toolCall->getName(), json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE)));
                            $result = call_user_func($toolHandler, ...$toolCall->getArguments());
                            if ($result) {
                                $toolCallsResults[$toolCall->getId()] = [
                                    'call' => sprintf('%s(%s)', $toolCall->getName(), implode(', ', $toolCall->getArguments())),
                                    'result' => $result,
                                ];
                                if ($this->isDebug()) {
                                    $this->observer?->debug(sprintf('Tool %s returned %s', $toolCall->getName(), json_encode($result, JSON_UNESCAPED_UNICODE)));
                                } else {
                                    $this->observer?->info(sprintf('Tool %s returned', $toolCall->getName()));
                                }
                            } else {
                                $this->observer?->info(sprintf('Tool %s returned nothing', $toolCall->getName()));
                            }
                        }
                    }
                    if ($toolCallsResults) {
                        $toolCallsMessages = [];
                        foreach ($toolCallsResults as $toolCallId => $toolCallResult) {
                            if (! $toolCallResult['call'] || ! $toolCallResult['result']) {
                                continue;
                            }
                            $toolCallsMessages[] = new ToolMessage(sprintf("Tool Call: %s\nObservation: %s", $toolCallResult['call'], json_encode($toolCallResult['result'], JSON_UNESCAPED_UNICODE)), $toolCallId);
                        }
                        $messages = $this->memory->getConversations($conversationId);
                        $innerChatResponse = $this->innerChat(array_merge($messages, [
                            $currentStageUserMessage,
                            $response->getFirstChoice()->getMessage()
                        ], $toolCallsMessages), conversationId: $conversationId);
                        $response = $innerChatResponse;
                    }
                }
            }
        }
        $this->memory->addMessages($currentStageUserMessage, $conversationId);
        $response->getFirstChoice() && $this->memory->addMessages($response->getFirstChoice()
            ?->getMessage(), $conversationId);
        return $this->response($response);
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;
        $this->observer?->setDebug($debug);
        return $this;
    }

    protected function innerChat(
        array $messages,
        string $conversationId,
        float $temperature = 0.5,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
    ) {
        if ($this->currentIteration >= $this->maxIterations) {
            throw new InvalidArgumentException('The maximum iterations has been reached.');
        }
        if ($this->isDebug()) {
            $this->observer?->debug('Inner call to the model with messages ' . implode("\r", array_map(function (
                    $message
                ) {
                    return sprintf('%s Prompt: %s', $message->getRole()->name, $message->getContent());
                }, $messages)));
        } else {
            $this->observer?->info('Inner call to the model');
        }
        $messages = $this->transferMessages($messages);
        $response = $this->model->chat($messages, $temperature, $maxTokens, $stop, $tools);
        if ($response instanceof ChatCompletionResponse) {
            $message = $response->getFirstChoice()->getMessage();
            if ($this->isDebug()) {
                $this->observer?->debug(sprintf('Model response %s message: %s', $message->getRole()->value, $message->getContent()));
            } else {
                $this->observer?->info('Model has responded');
            }
        }
        ++$this->currentIteration;
        return $response;
    }

    /**
     * @param \Hyperf\Odin\Message\MessageInterface[] $messages
     */
    protected function chat(
        array $messages,
        string $conversationId,
        float $temperature = 0.5,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
    ) {
        if ($this->currentIteration >= $this->maxIterations) {
            throw new InvalidArgumentException('The maximum iterations has been reached.');
        }
        if ($this->isDebug()) {
            $this->observer?->debug('Chatting to the model with messages ' . implode("\r", array_map(function (
                    $message
                ) {
                    return sprintf('%s Prompt: %s', $message->getRole()->name, $message->getContent());
                }, $messages)));
        } else {
            $this->observer?->info('Chatting to the model');
        }
        $messages = $this->transferMessages($messages);
        $response = $this->model->chat($messages, $temperature, $maxTokens, $stop, $tools);
        if ($response instanceof ChatCompletionResponse) {
            $firstChoice = $response->getFirstChoice();
            if (! $firstChoice) {
                var_dump($response);
            }
            $message = $firstChoice?->getMessage();
            if ($this->isDebug()) {
                $this->observer?->debug(sprintf('Model response %s message: %s', $message->getRole()->value, $message->getContent()));
            } else {
                $this->observer?->info('Model has responded');
            }
        }
        ++$this->currentIteration;
        return $response;
    }

    protected function response(ChatCompletionResponse $response): ChatCompletionResponse
    {
        if ($this->model instanceof SkylarkModel) {
            $choices = $response->getChoices();
            // 取 <|Answer|>: 后面的内容作为回答
            foreach ($choices as $key => $choice) {
                if (! $choice instanceof ChatCompletionChoice) {
                    continue;
                }
                $message = $choice->getMessage();
                if ($message instanceof AssistantMessage) {
                    $content = $message->getContent();
                    // 如果存在 <|Answer|> 标记，则取出 <|Answer|>: 后面的内容作为回答
                    if (str_contains($content, '<|Answer|>:')) {
                        $answer = substr($content, strpos($content, '<|Answer|>:') + 11);
                        $message->setContent($answer);
                        $choice->setMessage($message);
                        break;
                    }
                }
            }
        }
        return $response;
    }

    protected function transferMessages(array $messages): array
    {
        if ($this->model instanceof SkylarkModel) {
            // 把里面的 ToolMessage 转为 FunctionMessage
            foreach ($messages as $key => $message) {
                if ($message instanceof ToolMessage) {
                    $assistantMessage = new FunctionMessage($message->getContent(), $message->getToolCallId());
                    $messages[$key] = $assistantMessage;
                }
            }
        }
        return $messages;
    }
}

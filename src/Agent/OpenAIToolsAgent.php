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
use Hyperf\Odin\Model\ModelInterface;
use Hyperf\Odin\Observer;
use Hyperf\Odin\Prompt\PromptInterface;
use Hyperf\Odin\Tools\ToolInterface;
use InvalidArgumentException;

class OpenAIToolsAgent
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
                if (! $choice instanceof ChatCompletionChoice || ! $choice->isFinishedByToolCall() || ! $choice->getMessage() instanceof AssistantMessage) {
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
                        foreach ($toolCallsResults as $toolCallResult) {
                            if (! $toolCallResult['call'] || ! $toolCallResult['result']) {
                                continue;
                            }
                            $toolCallsMessages[] = new FunctionMessage("Tool Call: {call}\nObservation: {observation}", [
                                'call' => $toolCallResult['call'],
                                'observation' => json_encode($toolCallResult['result'], JSON_UNESCAPED_UNICODE),
                            ]);
                        }
                        $messages = $this->memory->getConversations($conversationId);
                        return $this->innerChat(array_merge($messages, $toolCallsMessages, [$currentStageUserMessage]), conversationId: $conversationId);
                    }
                } else {
                    $this->memory->addMessages([$currentStageUserMessage], $conversationId);
                }
            }
        }
        return $response;
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
        $response = $this->model->chat($messages, $temperature, $maxTokens, $stop, $tools);
        if ($response instanceof ChatCompletionResponse) {

        }
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
            $this->observer?->debug('Chatting to the model with messages ' . implode("\r", array_map(function ($message
                ) {
                    return sprintf('%s Prompt: %s', $message->getRole()->name, $message->getContent());
                }, $messages)));
        } else {
            $this->observer?->info('Chatting to the model');
        }
        var_dump($messages);
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
}

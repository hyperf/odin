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

namespace Hyperf\Odin\Conversation;

use Hyperf\Odin\Apis\ClientInterface;
use Hyperf\Odin\Apis\OpenAI\Request\FunctionCallDefinition;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionChoice;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Apis\OpenAI\Response\FunctionCall;
use Hyperf\Odin\LLM;
use Hyperf\Odin\Memory\AbstractMemory;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model;
use Hyperf\Odin\ModelMapper;
use Hyperf\Odin\ModelSelector;
use Hyperf\Odin\Prompt\Prompt;
use Hyperf\Odin\Utils\ArrFilter;
use InvalidArgumentException;

class Conversation
{
    protected ?ClientInterface $client = null;

    protected ?AbstractMemory $memory = null;

    protected ?string $conversationId = null;

    protected array $actions = [];

    public function __construct(protected LLM $llm, protected ModelMapper $modelMapper)
    {
    }

    public function chatToAgent(string $agent, string $message)
    {

    }

    public function chat(
        array $messages,
        string|Model|ModelSelector $model = null,
        Option $option = null,
        ?string $conversationId = null,
        string $chatType = 'user'
    ): ChatCompletionResponse {
        if (isset($messages[0]) || isset($messages[1])) {
            $messages = [
                'system' => $messages[0] ?? '',
                'user' => $messages[1] ?? '',
            ];
        }
        if (! isset($messages['user']) || ! isset($messages['system'])) {
            throw new InvalidArgumentException('The messages must contain user and system.');
        }
        if (! $messages['user'] instanceof UserMessage || ! $messages['system'] instanceof SystemMessage) {
            throw new InvalidArgumentException('The messages must be UserMessage and SystemMessage.');
        }
        if (! $messages['user']->getContext('original_user_message')) {
            $messages['user']->setContext('original_user_message', $messages['user']->getContent());
        }
        $originalUserMessage = $messages['user']->getContext('original_user_message');
        // Memory, handle the user message.
        if ($this->memory && $conversationId) {
            $memoryPrompt = $this->memory->buildPrompt($messages['user'], $conversationId);
            if (is_string($memoryPrompt)) {
                $messages['user']->setContent($memoryPrompt);
            }
            if ($chatType === 'user') {
                $this->memory->addHumanMessage($originalUserMessage, $conversationId, 'User Input: ');
            } elseif ($chatType === 'ai') {
                $this->memory->addAIMessage($originalUserMessage, $conversationId, 'AI Input: ');
            }
        }
        // Select Model
        if ($model instanceof ModelSelector) {
            $model = $model->select($messages['user']->getContent(), $this->modelMapper->getModels());
        } elseif (is_string($model)) {
            $model = $this->modelMapper->getModel($model);
        } elseif (! $model instanceof Model) {
            throw new InvalidArgumentException('The model must be a ModelSelector, Model or string.');
        }
        // Chat with the model.
        $response = $this->chatWithModel($messages, $model, $option);
        // Function Call Handlers
        if ($response->getChoices()) {
            foreach ($response->getChoices() as $choice) {
                if (! $choice instanceof ChatCompletionChoice || ! $choice->isFinishedByFunctionCall()) {
                    continue;
                }
                $message = $choice->getMessage();
                if (! $message instanceof AssistantMessage) {
                    continue;
                }
                $functionCall = $message->getFunctionCall();
                if (! $functionCall instanceof FunctionCall) {
                    continue;
                }
                /** @var FunctionCallDefinition[] $functionDefinitions */
                $functionDefinitions = ArrFilter::filterInstance(FunctionCallDefinition::class, $option->getFunctions());
                if ($functionCall->isShouldFix()) {
                    $functionCall = $this->fixFunctionCall($functionCall, $functionDefinitions);
                }
                foreach ($functionDefinitions as $functionDefinition) {
                    if ($functionDefinition->getName() === $functionCall->getName()) {
                        $functionCallHandlers = $functionDefinition->getFunctionCallHandlers();
                        if (isset($functionCallHandlers[0]) && is_callable($functionCallHandlers[0])) {
                            $functionCallHandler = $functionCallHandlers[0];
                            $result = $functionCallHandler($functionCall);
                            $code = $functionCall->getArguments()['code'] ?? '';
                            $prompt = Prompt::getPrompt('AfterCodeExecuted', [
                                    'userRequirement' => $messages['user']->getContext('original_user_message'),
                                    'code' => $code,
                                    'codeExecutedResult' => $result,
                                ]);
                            $messages = [
                                'system' => $messages['system'],
                                'user' => $messages['user']->setContent($prompt),
                            ];
                            $response = $this->chat($messages, $model, $option);
                        }
                        break;
                    }
                }
            }
        }
        // Memory, handle the AI message.
        if ($this->memory && $conversationId) {
            $this->memory->addAIMessage((string)$response, $conversationId, 'AI Response: ');
        }
        return $response;
    }

    public function withClient(ClientInterface $client): static
    {
        $static = clone $this;
        $static->client = $client;
        return $static;
    }

    public function withMemory(AbstractMemory $memory): static
    {
        $static = clone $this;
        $static->memory = $memory;
        return $static;
    }

    public function withActions(array $actions): static
    {
        $static = clone $this;
        $static->actions = $actions;
        return $static;
    }

    public function createConversationId(): string
    {
        return uniqid();
    }

    protected function chatWithModel(array $messages, Model $model, Option $option = null): ChatCompletionResponse
    {
        var_dump($messages['user']->getContent());
        return $this->llm->chat(messages: $messages, temperature: $option->getTemperature(), maxTokens: $option->getMaxTokens(), stop: $option->getStop(), functions: $option->getFunctions(), model: $model->getName(),);
    }

    protected function fixFunctionCall(FunctionCall $functionCall, array $functionDefinitions): FunctionCall
    {
        foreach ($functionDefinitions as $functionDefinition) {
            if (! $functionDefinition instanceof FunctionCallDefinition) {
                continue;
            }
            $functionCallHandlers = $functionDefinition->getFunctionCallHandlers();
            if (isset($functionCallHandlers[1]) && is_callable($functionCallHandlers[1])) {
                $functionCallHandler = $functionCallHandlers[1];
                $functionCall = $functionCallHandler($functionCall);
            }
            break;
        }
        return $functionCall;
    }
}

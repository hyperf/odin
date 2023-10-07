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

use Hyperf\Odin\Action\ActionTemplate;
use Hyperf\Odin\Action\CalculatorAction;
use Hyperf\Odin\Action\SearchAction;
use Hyperf\Odin\Action\WeatherAction;
use Hyperf\Odin\Apis\ClientInterface;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\LLM;
use Hyperf\Odin\Memory\AbstractMemory;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Stringable\Str;

class ConversationBak
{
    public function __construct(protected LLM $llm)
    {
    }

    public function chat(
        string $input,
        string $model,
        ?string $conversationId = null,
        AbstractMemory $memory = null,
        array $actions = [],
        ?ClientInterface $client = null,
    ): string {
        $finalAnswer = '';
        $prompt = $input;
        if ($actions) {
            $matchedActions = $this->thoughtActions($client, $input, $model, $actions);
            if ($matchedActions) {
                $actionsResults = $this->handleActions($matchedActions);
                $actionsResults && $prompt = (new ActionTemplate())->buildAfterActionExecutedPrompt($input, $actionsResults);
                if ($memory) {
                    $prompt = $memory->buildPrompt($prompt, $conversationId);
                }
                $response = $this->answer($client, $prompt, $model);
                if ($response->getContent()) {
                    $finalAnswer = Str::replaceFirst('Answer:', '', (string)$response);
                }
            }
        }
        if (! $finalAnswer) {
            if ($memory) {
                $prompt = $memory->buildPrompt($input, $conversationId);
            }
            $response = $this->answer($client, $prompt, $model);
            $finalAnswer = (string)$response;
        }
        if ($memory) {
            $memory->addHumanMessage($input, $conversationId);
            foreach ($actionsResults ?? [] as $actionName => $actionResult) {
                $memory->addMessage(sprintf('%s Action Result: %s', $actionName, $actionResult), $conversationId);
            }
            $memory->addAIMessage($finalAnswer, $conversationId);
        }
        return trim($finalAnswer);
    }

    public function createConversationId(): string
    {
        return uniqid();
    }

    protected function thoughtActions(
        ClientInterface $client,
        string $userInput,
        string $model,
        array $actions,
    ): array {
        $actionTemplate = new ActionTemplate();
        $prompt = $actionTemplate->buildThoughtActionsPrompt($userInput, $actions);
        $messages = [
            'system' => $this->buildSystemMessage(),
            'user' => new UserMessage($prompt),
        ];
        $response = $client->chat($messages, $model, temperature: 0);
        return $actionTemplate->parseActions($response);
    }

    protected function buildSystemMessage(): SystemMessage
    {
        return new SystemMessage('你是一个由 Hyperf 组织开发的聊天机器人，你必须严格按照格式要求返回内容');
    }

    protected function handleActions(array $matchedActions): array
    {
        // 匹配到了 Actions，按顺序执行 Actions
        $actionsResults = [];
        foreach ($matchedActions as $action) {
            if (! isset($action['name'], $action['args'])) {
                continue;
            }
            $actionName = $action['name'];
            $actionArgs = $action['args'];
            $actionInstance = match ($actionName) {
                'Calculator' => new CalculatorAction(),
                'Weather' => new WeatherAction(),
                'Search' => new SearchAction(),
                default => null,
            };
            if (! $actionInstance) {
                continue;
            }
            $actionResult = $actionInstance->handle(...$actionArgs);
            if ($actionResult) {
                $actionsResults[$actionName] = $actionResult;
            }
        }
        return $actionsResults;
    }

    protected function answer(
        ClientInterface $client,
        string $prompt,
        string $model,
        float $temperature = 0,
    ): ChatCompletionResponse {
        $messages = [
            'system' => $this->buildSystemMessage(),
            'user' => new UserMessage($prompt),
        ];
        return $client->chat($messages, $model, temperature: $temperature);
    }
}

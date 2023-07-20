<?php

namespace Hyperf\Odin\Conversation;


use Hyperf\Odin\Action\ActionTemplate;
use Hyperf\Odin\Action\CalculatorAction;
use Hyperf\Odin\Action\SearchAction;
use Hyperf\Odin\Action\WeatherAction;
use Hyperf\Odin\Apis\OpenAI\Client;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Apis\SystemMessage;
use Hyperf\Odin\Apis\UserMessage;
use Hyperf\Odin\Memory\AbstractMemory;
use Hyperf\Stringable\Str;

class Conversation
{

    public function chat(
        Client $client,
        string $input,
        string $model,
        ?string $conversationId = null,
        AbstractMemory $memory = null,
        array $actions = [],
    ): string {
        $finalAnswer = '';
        $prompt = $input;
        if ($actions) {
            $matchedActions = $this->thoughtActions($client, $input, $model, $actions);
            if ($matchedActions) {
                $actionsResults = $this->handleActions($matchedActions);
                $prompt = (new ActionTemplate())->buildAfterActionExecutedPrompt($input, $actionsResults);
                if ($memory) {
                    $prompt = $memory->buildPrompt($prompt, $conversationId);
                }
                $response = $this->answer($client, $prompt, $model);
                if ($response->getContent()) {
                    $finalAnswer = Str::replaceFirst('Final Answer:', '', $response);
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
            $memory->addAIMessage($finalAnswer, $conversationId);
        }
        return trim($finalAnswer);
    }

    protected function thoughtActions(
        Client $client,
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
        return new SystemMessage('你是一个由 Hyperf 组织开发的聊天机器人');
    }

    protected function handleActions(array $matchedActions): array
    {
        // 匹配到了 Actions，按顺序执行 Actions
        $actionsResults = [];
        foreach ($matchedActions as $action) {
            if (! isset($action['action'], $action['args'])) {
                continue;
            }
            $actionName = $action['action'];
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
            $actionsResults[$actionName] = $actionResult;
        }
        return $actionsResults;
    }

    protected function answer(
        Client $client,
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
<?php

namespace Hyperf\Odin\Conversation;


use Hyperf\Odin\Action\ActionTemplate;
use Hyperf\Odin\Action\CalculatorAction;
use Hyperf\Odin\Action\WeatherAction;
use Hyperf\Odin\Apis\OpenAI\Client;
use Hyperf\Odin\Apis\SystemMessage;
use Hyperf\Odin\Apis\UserMessage;
use Hyperf\Odin\Memory\AbstractMemory;

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
        $retryTimes = 0;
        retry:
        if ($actions) {
            $stop = ['Observation:', '\nObservation:', '\n\tObservation:'];
        }
        $response = $this->handle($client, [
            'system' => $this->buildSystemMessage(),
            'user' => $this->buildUserMessage($input, $conversationId, $memory, $actions),
        ], $model, $conversationId, $memory, $actions, $stop);
        if (! str_contains($response, 'Final Answer: ')) {
            $retryTimes++;
            if ($retryTimes < 3)
                goto retry;
        }
        return substr($response, strpos($response, 'Final Answer: ') + strlen('Final Answer: '));
    }

    protected function handle(
        Client $client,
        array $messages,
        string $model,
        ?string $conversationId = null,
        AbstractMemory $memory = null,
        array $actions = [],
        array $stop = [],
    ): string {
        var_dump('userMessage:' . $messages['user']->getContent());
        if (! $conversationId) {
            $conversationId = uniqid();
        }
        $response = $client->chat($messages, $model, temperature: 0, stop: $stop);
        if ($memory) {
            if (isset($messages['user']) && $messages['user'] instanceof UserMessage) {
                $memory->addHumanMessage($messages['user']->getContent(), $conversationId);
                $memory->addAIMessage($response, $conversationId);
            }
        }
        var_dump('Response:' . (string)$response);
        if ($actions && $stop) {
            // 解析 $response 中的 Action 和 Action Input
            $actionTemplate = new ActionTemplate();
            $actions = $actionTemplate->parseResponse($response);
            $actionCount = count($actions);
            $index = 0;
            foreach ($actions as $action) {
                $actionInstance = null;
                switch ($action['action']) {
                    case 'Calculator':
                        $actionInstance = new CalculatorAction();
                        break;
                    case 'Weather':
                        $actionInstance = new WeatherAction();
                        break;
                }
                $actionResult = $actionInstance->handle(...$action['args']);
                $userMessage = $messages['user'];
                if ($actionCount > 1) {
                    if ($index === 0) {
                        $userMessage->appendContent(
                            $response . PHP_EOL . 'Observation: ' . PHP_EOL . '- ' . $action['action'] . ': ' . $actionResult . PHP_EOL
                        );
                    } else {
                        $userMessage->appendContent(
                            '- ' . $action['action'] . ': ' . $actionResult . PHP_EOL
                        );
                    }
                    $index++;
                } else {
                    $userMessage->appendContent(
                        $response . PHP_EOL . 'Observation: ' . $actionResult
                    );
                }
            }
            return $this->handle($client, $messages, $model, $conversationId, $memory, $actions);

        }
        return (string)$response;
    }

    protected function buildUserMessage(
        string $input,
        ?string $conversationId,
        ?AbstractMemory $memory,
        array $actions = []
    ): UserMessage {
        if ($memory) {
            $prompt = $memory->buildPrompt($input, $conversationId);
        } elseif ($actions) {
            $actionTemplate = new ActionTemplate();
            $prompt = $actionTemplate->buildPrompt($input, $actions);
        } else {
            $prompt = $input;
        }
        return new UserMessage($prompt);
    }

    protected function buildSystemMessage(): SystemMessage
    {
        return new SystemMessage('You are an AI that created by Hyperf organization.');
    }

}
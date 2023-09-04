<?php

namespace Hyperf\Odin\Agent;


use Hyperf\Codec\Exception\InvalidArgumentException;
use Hyperf\Codec\Json;
use Hyperf\Odin\Action\ActionFactory;
use Hyperf\Odin\Apis\ClientInterface;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Memory\AbstractMemory;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Prompt\AgentPromptTemplate;
use Hyperf\Stringable\Str;

class Agent
{

    public function chat(
        ClientInterface $client,
        string $input,
        string $model,
        ?string $conversationId = null,
        AbstractMemory $memory = null,
        array $actions = [],
        string $agentThoughtAndObservation = '',
    ): string {
        $prompt = $this->buildPrompt($input, $agentThoughtAndObservation, $memory, $conversationId, $actions);
        $response = $client->chat([
            'system' => $this->buildSystemMessage(),
            'user' => new UserMessage($prompt),
        ], $model, temperature: 0, stop: ['Observation:', 'Observation:\n', 'Observation:\n\n']);
        [$result, $isFinalAnswer] = $this->parseAgentOutput($response, $client);
        if (! $isFinalAnswer) {
            foreach ($result as $item) {
                $agentThoughtAndObservation .= "    " . $item . "\n";
            }
            return $this->chat($client, $input, $model, $conversationId, $memory, $actions, $agentThoughtAndObservation);
        } else {
            $finalAnswer = end($result);
            $finalAnswer = trim(Str::replaceFirst('Final Answer:', '', $finalAnswer));
        }
        return trim($finalAnswer);
    }

    protected function parseAgentOutput(ChatCompletionResponse $response, ClientInterface $client): array
    {
        $result = [];
        $isFinalAnswer = false;
        $currentChoice = current($response->getChoices());
        $currentChoiceMessage = trim($currentChoice?->getMessage()?->getContent() ?? '');
        $observationPrefix = 'Observation: ';
        $actionPrefix = 'Action:';
        $thoughtPrefix = 'Thought:';
        $finalAnswerPrefix = 'Final Answer:';
        $lines = explode("\n", $currentChoiceMessage);
        foreach ($lines as $line) {
            if (str_starts_with($line, $thoughtPrefix)) {
                $result[] = trim($line);
            } elseif (str_starts_with($line, $actionPrefix) && $line !== ($actionPrefix . ' null')) {
                $line = trim($line);
                $result[] = $line;
                $actionJson = trim(Str::replaceFirst($actionPrefix, '', $line));
                try {
                    $action = Json::decode($actionJson);
                    $actionResult = $this->handleAction($action, $client);
                    if ($actionResult) {
                        $result[] = $observationPrefix . $actionResult;
                    }
                } catch (InvalidArgumentException $exception) {
                    var_dump($exception->getMessage(), $line);
                    exit();
                }
            } elseif (str_starts_with($line, $finalAnswerPrefix)) {
                $isFinalAnswer = true;
                $result[] = trim($line);
            } else {
                continue;
            }
        }

        return [$result, $isFinalAnswer];
    }

    protected function handleAction(array $action, ClientInterface $client)
    {
        if (! isset($action['name'], $action['args'])) {
            return null;
        }
        $name = $action['name'];
        $args = $action['args'];
        $actionFactory = new ActionFactory();
        try {
            $instance = $actionFactory->create($name);
            $instance->setClient($client);
            if (! method_exists($instance, 'handle')) {
                return null;
            }
            return $instance->handle(...$args);
        } catch (\InvalidArgumentException $exception) {
            return null;
        }
    }

    protected function buildPrompt(
        string $input,
        string $agentThoughtAndObservation,
        ?AbstractMemory $memory,
        ?string $conversationId,
        array $actions
    ): string {
        $template = new AgentPromptTemplate();
        return $template->build($input, $agentThoughtAndObservation, $actions);
    }

    protected function buildSystemMessage(): SystemMessage
    {
        return new SystemMessage('You are a robot developed by the Hyperf organization, you must return content in strict format requirements.');
    }

}
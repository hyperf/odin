<?php

namespace Hyperf\Odin\Conversation;


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
    ): string
    {
        if (! $conversationId) {
            $conversationId = uniqid();
        }
        $response = $client->chat([
            new SystemMessage('You are an AI that created by Hyperf organization.'),
            new UserMessage($this->buildUserMessage($input, $conversationId, $memory)),
        ], $model);
        if ($memory) {
            $memory->addHumanMessage($input, $conversationId);
            $memory->addAIMessage($response, $conversationId);
        }
        return (string)$response;
    }

    protected function buildUserMessage(string $input, ?string $conversationId, ?AbstractMemory $memory): string
    {
        if ($memory) {
            return $memory->buildPrompt($input, $conversationId);
        }
        return $input;
    }

}
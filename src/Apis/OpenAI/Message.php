<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

class Message
{

    public static function fromArray(array $message): MessageInterface
    {
        return match ($message['role']) {
            'assistant' => new AssistantMessage($message['content'] ?? '', $message['function_call'] ?? []),
            'system' => new SystemMessage($message['content'] ?? ''),
            default => new UserMessage($message['content'] ?? ''),
        };
    }


}
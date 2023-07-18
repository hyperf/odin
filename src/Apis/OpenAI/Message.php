<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Apis\AssistantMessage;
use Hyperf\Odin\Apis\MessageInterface;
use Hyperf\Odin\Apis\SystemMessage;
use Hyperf\Odin\Apis\UserMessage;

class Message
{

    public static function fromArray(array $message): MessageInterface
    {
        return match ($message['role']) {
            'assistant' => new AssistantMessage($message['content'] ?? ''),
            'system' => new SystemMessage($message['content'] ?? ''),
            default => new UserMessage($message['content'] ?? ''),
        };
    }


}
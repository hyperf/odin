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
            'assistant' => AssistantMessage::fromArray($message),
            'system' => SystemMessage::fromArray($message),
            default => UserMessage::fromArray($message),
        };
    }
}

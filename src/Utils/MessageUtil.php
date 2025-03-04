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

namespace Hyperf\Odin\Utils;

use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;

class MessageUtil
{
    /**
     * 过滤消息数组，只保留 MessageInterface 类型的消息.
     * @param array<MessageInterface> $messages
     */
    public static function filter(array $messages): array
    {
        $messagesArr = [];
        foreach ($messages as $message) {
            if ($message instanceof SystemMessage && $message->getContent() === '') {
                continue;
            }
            if ($message instanceof MessageInterface) {
                $messagesArr[] = $message->toArray();
            }
        }
        return $messagesArr;
    }

    public static function createFromArray(array $message): ?MessageInterface
    {
        if (! isset($message['role'])) {
            return null;
        }
        return match ($message['role']) {
            'assistant' => AssistantMessage::fromArray($message),
            'system' => SystemMessage::fromArray($message),
            'tool' => isset($message['tool_call_id']) ? ToolMessage::fromArray($message) : null,
            default => UserMessage::fromArray($message),
        };
    }
}

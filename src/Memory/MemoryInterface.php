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

namespace Hyperf\Odin\Memory;

use Hyperf\Odin\Message\MessageInterface;
use Stringable;

interface MemoryInterface
{
    public function setSystemMessage(
        MessageInterface $message,
        string|Stringable $conversationId
    ): static;

    public function addMessages(
        array|MessageInterface $messages,
        string|Stringable $conversationId
    ): static;

    public function getConversations(string $conversationId): array;
}

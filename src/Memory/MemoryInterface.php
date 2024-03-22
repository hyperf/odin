<?php

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
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
use InvalidArgumentException;
use Stringable;

class MessageHistory extends AbstractMemory
{
    protected array $systemMessages = [];

    public function __construct(
        protected int $maxRecord = 10,
        protected int $maxTokens = 1000,
        array $conversations = []
    ) {
        if ($maxTokens <= 0) {
            throw new InvalidArgumentException('maxTokens must be greater than zero.');
        }
    }

    public function setSystemMessage(MessageInterface $message, string|Stringable $conversationId): static
    {
        $this->systemMessages[$conversationId] = $message;
        return $this;
    }

    public function addMessages(array|MessageInterface $messages, string|Stringable $conversationId): static
    {
        if (! is_string($conversationId) && ! ($conversationId instanceof Stringable)) {
            throw new InvalidArgumentException('Conversation ID must be a string, an instance of Stringable, or null.');
        }

        if (! is_array($messages)) {
            $messages = [$messages];
        }

        foreach ($messages as $message) {
            if (! $message instanceof MessageInterface) {
                throw new InvalidArgumentException('Messages must be an array of MessageInterface instances.');
            }
        }

        foreach ($messages as $message) {
            $this->conversations[$conversationId][] = $message;
            // Ensure the number of messages does not exceed maxRecord
            if (count($this->conversations[$conversationId]) > $this->maxRecord) {
                $this->conversations[$conversationId] = array_slice($this->conversations[$conversationId], -$this->maxRecord);
            }
        }

        return $this;
    }

    public function getConversations(string $conversationId): array
    {
        $messages = $this->conversations[$conversationId] ?? [];
        $systemMessage = $this->systemMessages[$conversationId] ?? null;
        return $systemMessage ? array_merge([$systemMessage], $messages) : $messages;
    }
}

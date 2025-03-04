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

use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;

/**
 * 该类是为了减少已有逻辑的改动.
 */
class MessageHistory
{
    /**
     * @var array<string, MemoryManager> 会话历史
     */
    protected array $conversations = [];

    public function __construct(
        protected int $maxRecord = 10,
        protected int $maxTokens = 1000,
        array $conversations = []
    ) {}

    public function count(): int
    {
        return count($this->conversations);
    }

    public function setSystemMessage(MessageInterface $message, string $conversationId): self
    {
        $memory = $this->getMemoryManager($conversationId);
        $memory->addSystemMessage($message);
        return $this;
    }

    public function addMessages(array|MessageInterface $messages, string $conversationId): self
    {
        if (! is_array($messages)) {
            $messages = [$messages];
        }
        $memory = $this->getMemoryManager($conversationId);
        foreach ($messages as $message) {
            $memory->addMessage($message);
        }
        return $this;
    }

    public function getConversations(string $conversationId): array
    {
        $memory = $this->getMemoryManager($conversationId);
        return $memory->applyPolicy()->getProcessedMessages();
    }

    public function getMemoryManager(string $conversationId, ?int $maxRecord = null): MemoryManager
    {
        if (! isset($this->conversations[$conversationId])) {
            $memoryManager = new MemoryManager(policy: new LimitCountPolicy(['max_count' => $maxRecord ?? $this->maxRecord]));
            $this->conversations[$conversationId] = $memoryManager;
        }
        return $this->conversations[$conversationId];
    }

    public function clear(string $conversationId): self
    {
        if (! isset($this->conversations[$conversationId])) {
            return $this;
        }
        $this->conversations[$conversationId]->clear();
        return $this;
    }
}

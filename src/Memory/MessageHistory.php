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

use Stringable;

class MessageHistory extends AbstractMemory
{
    public function __construct(
        protected int $maxRecord = 10,
        protected int $maxTokens = 1000,
        array $conversations = []
    ) {
        // @todo validate $maxTokens
    }

    public function buildPrompt(string|Stringable $input, string|Stringable|null $conversationId): string|Stringable
    {
        $conversation = $this->conversations[$conversationId] ?? null;
        if (! $conversation) {
            return $input;
        }
        $history = implode("\n", $conversation);
        return <<<EOF
"The following is a conversation history between a user and AI：

{$history}
 
User: {$input}
AI: 
EOF;
    }

    public function addHumanMessage(
        string|Stringable $input,
        string|Stringable|null $conversationId,
        string $prefix = 'User: '
    ): static
    {
        return $this->addMessage($prefix . $input, $conversationId);
    }

    public function addAIMessage(
        string|Stringable $output,
        string|Stringable|null $conversationId,
        string $prefix = 'AI: '
    ): static
    {
        return $this->addMessage($prefix . $output, $conversationId);
    }

    public function addMessage(string|Stringable $message, string|Stringable|null $conversationId): static
    {
        if ($conversationId) {
            $this->conversations[$conversationId][] = $message;
            if (count($this->conversations[$conversationId]) > $this->maxRecord) {
                array_shift($this->conversations[$conversationId]);
            }
        }
        return $this;
    }
}

<?php

namespace Hyperf\Odin\Memory;


class MessageHistory extends AbstractMemory
{
    public function __construct(protected int $maxRecord = 10, protected int $maxTokens = 1000, protected array $conversations = [])
    {
        // @todo validate $maxTokens
    }

    public function buildPrompt(string $input, ?string $conversationId): string
    {
        $conversation = $this->conversations[$conversationId] ?? null;
        if (! $conversation) {
            $conversation = [
                'Null',
            ];
        }
        $history = implode("\n", $conversation);
        return <<<EOF
The following is the conversation history between a human and an AI, you should continue the conversation from the last line:

Current conversation:

$history

Human: $input
AI: 
EOF;
    }

    public function addHumanMessage(string $input, ?string $conversationId): static
    {
        if ($conversationId) {
            $this->conversations[$conversationId][] = 'Human: ' . $input;
            if (count($this->conversations[$conversationId]) > $this->maxRecord) {
                array_shift($this->conversations[$conversationId]);
            }
        }
        return $this;
    }

    public function addAIMessage(string $output, ?string $conversationId): static
    {
        if ($conversationId) {
            $this->conversations[$conversationId][] = 'AI: ' . $output;
            if (count($this->conversations[$conversationId]) > $this->maxRecord) {
                array_shift($this->conversations[$conversationId]);
            }
        }
        return $this;
    }
}
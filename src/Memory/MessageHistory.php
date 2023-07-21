<?php

namespace Hyperf\Odin\Memory;


class MessageHistory extends AbstractMemory
{
    public function __construct(
        protected int $maxRecord = 10,
        protected int $maxTokens = 1000,
        array $conversations = []
    ) {
        // @todo validate $maxTokens
    }

    public function buildPrompt(string $input, ?string $conversationId): string
    {
        $conversation = $this->conversations[$conversationId] ?? null;
        if (! $conversation) {
            return $input;
        }
        $history = implode("\n", $conversation);
        return <<<EOF
"以下是一段用户与AI的对话记录：

$history
 
用户: $input
AI: 
EOF;
    }

    public function addHumanMessage(string $input, ?string $conversationId): static
    {
        return $this->addMessage('用户: ' . $input, $conversationId);
    }

    public function addAIMessage(string $output, ?string $conversationId): static
    {
        return $this->addMessage('AI: ' . $output, $conversationId);
    }

    public function addMessage(string $message, ?string $conversationId): static
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
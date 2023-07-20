<?php

namespace Hyperf\Odin\Memory;


class MessageHistory extends AbstractMemory
{
    public function __construct(protected int $maxRecord = 10, protected int $maxTokens = 1000, array $conversations = [])
    {
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
"以下是一段用户与AI的历史对话：

$history

以上为历史对话。

以下为最新对话：
 
用户: $input
AI: 
EOF;
    }

    public function addHumanMessage(string $input, ?string $conversationId): static
    {
        if ($conversationId) {
            $this->conversations[$conversationId][] = '用户: ' . $input;
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
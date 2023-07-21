<?php

namespace Hyperf\Odin\Memory;


abstract class AbstractMemory
{

    protected array $conversations = [];

    abstract public function addHumanMessage(string $input, ?string $conversationId): static;

    abstract public function addAIMessage(string $output, ?string $conversationId): static;

    abstract public function addMessage(string $message, ?string $conversationId): static;

    abstract public function buildPrompt(string $input, ?string $conversationId): string;

    public function count(): int
    {
        return count($this->conversations);
    }

}
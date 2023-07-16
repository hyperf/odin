<?php

namespace Hyperf\Odin\Apis\OpenAI;


class Usage
{

    public function __construct(public int $promptTokens, public int $completionTokens, public int $totalTokens)
    {

    }

    public static function fromArray(array $usage): static
    {
        return new static($usage['prompt_tokens'], $usage['completion_tokens'], $usage['total_tokens']);
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

}
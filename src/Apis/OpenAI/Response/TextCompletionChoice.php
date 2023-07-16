<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


class TextCompletionChoice
{

    public function __construct(public string $text, public ?int $index = null, public ?string $logprobs = null, public ?string $finishReason = null)
    {
    }

    public static function fromArray(array $choice): static
    {
        return new static($choice['text'], $choice['index'] ?? null, $choice['logprobs'] ?? null, $choice['finish_reason'] ?? null);
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function getLogprobs(): ?string
    {
        return $this->logprobs;
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

}
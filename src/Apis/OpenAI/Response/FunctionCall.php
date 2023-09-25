<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


class FunctionCall
{

    public function __construct()
    {
    }

    public static function fromArray(array $choice): static
    {
        return new static($choice['text'], $choice['index'] ?? null, $choice['logprobs'] ?? null, $choice['finish_reason'] ?? null);
    }

}
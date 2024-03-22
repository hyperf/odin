<?php

namespace Hyperf\Odin\Model;


class OpenAIModel implements ModelInterface
{

    public function __construct(protected string $modelName, protected array $config)
    {
    }

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
    ) {

    }


}
<?php

namespace Hyperf\Odin\Model;

interface ModelInterface
{

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
    );

}
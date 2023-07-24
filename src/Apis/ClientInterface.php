<?php

namespace Hyperf\Odin\Apis;

interface ClientInterface
{


    public function chat(
        array $messages,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 1000,
        array $stop = []
    );

}
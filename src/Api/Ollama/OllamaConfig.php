<?php

namespace Hyperf\Odin\Api\Ollama;


class OllamaConfig
{

    public ?string $baseUrl = null;

    public function __construct(
        string $baseUrl = 'http://0.0.0.0:11434'
    ) {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
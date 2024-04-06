<?php

namespace Hyperf\Odin\Model;


use Hyperf\Odin\Apis\Ollama\Client;
use Hyperf\Odin\Apis\Ollama\Ollama;
use Hyperf\Odin\Apis\Ollama\OllamaConfig;

class OllamaModel implements ModelInterface
{

    public function __construct(public string $model, public array $config)
    {

    }

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
    ) {
        $client = $this->getOllamaClient();
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $tools);
    }

    public function getOllamaClient(): Client
    {
        $ollama = new Ollama();
        $config = new OllamaConfig($this->config['base_url'] ?? 'http://0.0.0.0:11434');
        return $ollama->getClient($config, $this->model);
    }
}
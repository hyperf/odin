<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Api\Ollama\Client;
use Hyperf\Odin\Api\Ollama\Ollama;
use Hyperf\Odin\Api\Ollama\OllamaConfig;
use Hyperf\Odin\Api\Ollama\Response\EmbeddingResponse;

class OllamaModel implements ModelInterface, EmbeddingInterface
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

    public function embedding(string $input): EmbeddingResponse
    {
        $client = $this->getOllamaClient();
        return $client->embedding($input, $this->model);
    }

    public function getOllamaClient(): Client
    {
        $ollama = new Ollama();
        $config = new OllamaConfig($this->config['base_url'] ?? 'http://0.0.0.0:11434');
        return $ollama->getClient($config);
    }

    public function getSpecifiedModelName(): string
    {
        return $this->model;
    }
}

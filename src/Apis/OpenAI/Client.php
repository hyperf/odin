<?php

namespace Hyperf\Odin\Apis\OpenAI;


use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Apis\MessageInterface;

class Client
{

    protected GuzzleClient $client;

    protected OpenAIConfig $config;

    public function __construct(OpenAIConfig $config)
    {
        $this->initConfig($config);
    }

    protected function initConfig(OpenAIConfig $config): static
    {
        if (! $config->getApiKey()) {
            throw new \InvalidArgumentException('API key of OpenAI api is required');
        }
        $headers = [
            'Authorization' => 'Bearer ' . $config->getApiKey(),
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hyperf-Odin/1.0'
        ];
        if ($config->getOrganization()) {
            $headers['OpenAI-Organization'] = $config->getOrganization();
        }
        $this->client = new GuzzleClient([
            'base_uri' => $config->baseUrl,
            'headers' => $headers
        ]);
        $this->config = $config;
        return $this;
    }

    public function completions(array $messages, string $model, float $temperature = 0.9, int $maxTokens = 200): Response
    {
        $messagesArr = [];
        foreach ($messages as $message) {
            if ($message instanceof MessageInterface) {
                $messagesArr[] = $message->toArray();
            }
        }
        $response = $this->client->post('/v1/chat/completions', [
            'json' => [
                'messages' => $messagesArr,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
        ]);
        return new Response($response);
    }

}
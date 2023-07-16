<?php

namespace Hyperf\Odin\Apis\AzureOpenAI;


use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Apis\MessageInterface;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Apis\OpenAI\Response\ListResponse;
use Hyperf\Odin\Apis\OpenAI\Response\TextCompletionResponse;
use Hyperf\Odin\Exception\NotImplementedException;
use InvalidArgumentException;

class Client extends \Hyperf\Odin\Apis\OpenAI\Client
{

    protected AzureOpenAIConfig|OpenAIConfig $config;

    protected function initConfig(AzureOpenAIConfig|OpenAIConfig $config): static
    {
        if (! $config instanceof AzureOpenAIConfig) {
            throw new InvalidArgumentException('AzureOpenAIConfig is required');
        }
        if (! $config->getApiKey()) {
            throw new InvalidArgumentException('API key of OpenAI api is required');
        }
        $headers = [
            'api-key' => $config->getApiKey(),
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hyperf-Odin/1.0',
        ];
        $this->client = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'headers' => $headers
        ]);
        $this->config = $config;
        return $this;
    }

    public function chat(array $messages, string $model, float $temperature = 0.9, int $maxTokens = 1000): ChatCompletionResponse
    {
        $deploymentPath = $this->buildDeploymentPath();
        $messagesArr = [];
        foreach ($messages as $message) {
            if ($message instanceof MessageInterface) {
                $messagesArr[] = $message->toArray();
            }
        }
        $response = $this->client->post($deploymentPath . '/chat/completions', [
            'query' => [
                'api-version' => $this->config->getApiVersion(),
            ],
            'json' => [
                'messages' => $messagesArr,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
        ]);
        return new ChatCompletionResponse($response);
    }

    public function completions(string $prompt, string $model, float $temperature = 0.9, int $maxTokens = 200): TextCompletionResponse
    {
        $deploymentPath = $this->buildDeploymentPath();
        $response = $this->client->post($deploymentPath . '/completions', [
            'query' => [
                'api-version' => $this->config->getApiVersion(),
            ],
            'json' => [
                'prompt' => $prompt,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
        ]);
        return new TextCompletionResponse($response);
    }

    public function models(): ListResponse
    {
        throw new NotImplementedException();
    }

    protected function buildDeploymentPath(): string
    {
        return 'openai/deployments/' . $this->config->getDeploymentName();
    }

}
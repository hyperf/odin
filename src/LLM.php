<?php

namespace Hyperf\Odin;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use function Hyperf\Support\env as env;

class LLM
{

    public string $model = 'gpt-3.5-turbo';
    public string $apiType = 'openai';

    public function __construct(ConfigInterface $config)
    {
        $this->apiType = $config->get('odin.llm.default_api', $this->apiType);
        $this->model = $config->get('odin.llm.default_model', $this->model);
    }

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 1000,
        array $stop = [],
        array $functions = [],
    ): Apis\OpenAI\Response\ChatCompletionResponse {
        $client = match ($this->apiType) {
            'openai' => $this->getOpenAIClient(),
            'azure' => $this->getAzureOpenAIClient(),
        };
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $functions);
    }

    public function getOpenAIClient(): OpenAIClient
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig(env('OPENAI_API_KEY'),);
        return $openAI->getClient($config);
    }

    public function getAzureOpenAIClient(): AzureOpenAIClient
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY'), baseUrl: env('AZURE_OPENAI_API_BASE'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
        return $openAI->getClient($config);
    }

}
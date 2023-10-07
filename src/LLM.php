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

namespace Hyperf\Odin;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\ClientInterface;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Conversation\Conversation;

class LLM
{
    public string $defaultModelName = 'gpt-3.5-turbo';

    public function __construct(protected ConfigInterface $config, protected ModelMapper $modelMapper)
    {
        $this->defaultModelName = $config->get('odin.llm.default', $this->defaultModelName);
    }

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $functions = [],
        string $model = '',
    ): Apis\OpenAI\Response\ChatCompletionResponse {
        $client = null;
        if (! $model) {
            $model = $this->defaultModelName;
        }
        $targetModel = $this->modelMapper->getModel($model);
        if (! $client) {
            $client = match ($targetModel->getApiType()) {
                'openai' => $this->getOpenAIClient(),
                'azure' => $this->getAzureOpenAIClient(),
            };
        }
        return $client->chat($messages, $this->defaultModelName, $temperature, $maxTokens, $stop, $functions);
    }

    public function getOpenAIClient(): OpenAIClient
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig($this->config->get('odin.openai.api_key', ''));
        return $openAI->getClient($config);
    }

    public function getAzureOpenAIClient(string|Model $model = ''): AzureOpenAIClient
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig($this->config->get('odin.azure', []));
        if (! $model) {
            $model = $this->defaultModelName;
        }
        if (is_string($model)) {
            $model = $this->modelMapper->getModel($model);
        }
        return $openAI->getClient($config, $model->getName());
    }

    public function createConversation(): Conversation
    {
        return new Conversation($this, $this->modelMapper);
    }

    protected function getClient(Model $model): ClientInterface
    {
        return match ($model->getApiType()) {
            'openai' => $this->getOpenAIClient(),
            'azure' => $this->getAzureOpenAIClient(),
        };
    }
}

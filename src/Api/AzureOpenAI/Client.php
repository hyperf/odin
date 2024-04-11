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

namespace Hyperf\Odin\Api\AzureOpenAI;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\ClientInterface;
use Hyperf\Odin\Api\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\OpenAI\Response\ListResponse;
use Hyperf\Odin\Api\OpenAI\Response\TextCompletionResponse;
use Hyperf\Odin\Exception\NotImplementedException;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Tool\ToolInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    protected AzureOpenAIConfig $config;

    /**
     * @var GuzzleClient[]
     */
    protected array $clients = [];

    protected ?LoggerInterface $logger;

    protected bool $debug = true;
    protected string $model;

    public function __construct(AzureOpenAIConfig $config, LoggerInterface $logger, string $model)
    {
        $this->logger = $logger;
        $this->model = $model;
        $this->initConfig($config);
    }

    public function chat(
        array $messages,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 1000,
        array $stop = [],
        array $tools = [],
        bool $stream = false,
    ): ChatCompletionResponse {
        $deploymentPath = $this->buildDeploymentPath($model);
        $messagesArr = [];
        foreach ($messages as $message) {
            if ($message instanceof MessageInterface) {
                $messagesArr[] = $message->toArray();
            }
        }
        $json = [
            'messages' => $messagesArr,
            'model' => $model,
            'temperature' => $temperature,
        ];
        if ($maxTokens) {
            $json['max_tokens'] = $maxTokens;
        }
        if (! empty($tools)) {
            $toolsArray = [];
            foreach ($tools as $tool) {
                if ($tool instanceof ToolInterface) {
                    $toolsArray[] = $tool->toToolDefinition()->toArray();
                } elseif ($tool instanceof ToolDefinition) {
                    $toolsArray[] = $tool->toArray();
                } else {
                    $toolsArray[] = $tool;
                }
            }
            if (! empty($toolsArray)) {
                $json['tools'] = $toolsArray;
                $json['tool_choice'] = 'auto';
            }
        }
        if ($stop) {
            $json['stop'] = $stop;
        }
        $this->debug && $this->logger?->debug(sprintf("Send Messages: %s\nTools: %s", json_encode($messagesArr, JSON_UNESCAPED_UNICODE), json_encode($tools, JSON_UNESCAPED_UNICODE)));
        $response = $this->getClient($model)->post($deploymentPath . '/chat/completions', [
            'query' => [
                'api-version' => $this->config->getApiVersion($model),
            ],
            'json' => $json,
            'verify' => false,
        ]);
        $chatCompletionResponse = new ChatCompletionResponse($response);
        $this->debug && $this->logger?->debug('Receive: ' . $chatCompletionResponse);
        return $chatCompletionResponse;
    }

    public function completions(
        string $prompt,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 200
    ): TextCompletionResponse {
        $deploymentPath = $this->buildDeploymentPath($model);
        $response = $this->getClient($model)->post($deploymentPath . '/completions', [
            'query' => [
                'api-version' => $this->config->getApiVersion($model),
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

    public function embedding(
        string $input,
        string $model = 'text-embedding-ada-002',
        ?string $user = null
    ): ListResponse {
        $deploymentPath = $this->buildDeploymentPath($model);
        $json = [
            'input' => $input,
        ];
        $user && $json['user'] = $user;
        $response = $this->getClient($model)->post($deploymentPath . '/embeddings', [
            'query' => [
                'api-version' => $this->config->getApiVersion($model),
            ],
            'json' => $json,
        ]);
        return new ListResponse($response);
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;
        return $this;
    }

    protected function initConfig(AzureOpenAIConfig $config): static
    {
        if (! $config instanceof AzureOpenAIConfig) {
            throw new InvalidArgumentException('AzureOpenAIConfig is required');
        }
        $this->config = $config;
        $headers = [
            'api-key' => $config->getApiKey(),
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hyperf-Odin/1.0',
        ];
        $this->clients[$this->model] = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'headers' => $headers,
        ]);
        return $this;
    }

    protected function getClient(string $model): ?GuzzleClient
    {
        return $this->clients[$model];
    }

    protected function buildDeploymentPath(string $model = 'gpt-3.5-turbo'): string
    {
        return 'openai/deployments/' . $this->config->getDeploymentName($model);
    }
}

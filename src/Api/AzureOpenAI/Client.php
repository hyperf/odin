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
use Hyperf\Odin\Api\StreamOption;
use Hyperf\Odin\Exception\NotImplementedException;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Tool\ToolInterface;
use Hyperf\Odin\Utils\StreamUtil;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    protected AzureOpenAIConfig $config;

    /**
     * @var GuzzleClient[]
     */
    protected array $clients = [];

    protected ?LoggerInterface $logger = null;

    protected bool $debug = false;

    protected string $model;

    public function __construct(AzureOpenAIConfig $config, ?LoggerInterface $logger, string $model)
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
        $deploymentPath = $this->buildDeploymentPath();
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
            'stream' => $stream,
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

        $options = [
            'query' => [
                'api-version' => $this->config->getApiVersion(),
            ],
            'json' => $json,
            'stream' => $stream,
        ];
        $url = $deploymentPath . '/chat/completions';

        $resource = null;
        $response = null;
        if (StreamUtil::enabledContext($stream)) {
            $baseUri = $this->getClient($model)->getConfig('base_uri');
            $options['headers'] = $this->getClient($model)->getConfig('headers');
            $resource = StreamUtil::createContext('POST', $baseUri . '/' . $url, $options);
        } else {
            $response = $this->getClient($model)->post($url, $options);
        }

        $chatCompletionResponse = new ChatCompletionResponse($response, $stream, $this->logger);
        $chatCompletionResponse->setResource($resource);
        $this->debug && $this->logger?->debug('Receive: ' . $chatCompletionResponse);
        return $chatCompletionResponse;
    }

    public function completions(
        string $prompt,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 200
    ): TextCompletionResponse {
        $deploymentPath = $this->buildDeploymentPath();
        $response = $this->getClient($model)->post($deploymentPath . '/completions', [
            'query' => [
                'api-version' => $this->config->getApiVersion(),
            ],
            'json' => [
                'prompt' => $prompt,
                'model' => $model,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ],
            'verify' => false,
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
        $deploymentPath = $this->buildDeploymentPath();
        $json = [
            'input' => $input,
        ];
        $user && $json['user'] = $user;
        $response = $this->getClient($model)->post($deploymentPath . '/embeddings', [
            'query' => [
                'api-version' => $this->config->getApiVersion(),
            ],
            'json' => $json,
            'verify' => false,
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

    protected function buildDeploymentPath(): string
    {
        return 'openai/deployments/' . $this->config->getDeploymentName();
    }
}

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

namespace Hyperf\Odin\Api\RWKV;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\ClientInterface;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\OpenAI\Response\ListResponse;
use Hyperf\Odin\Api\OpenAI\Response\TextCompletionResponse;
use Hyperf\Odin\Message\MessageInterface;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    protected GuzzleClient $client;

    protected RWKVConfig $config;

    protected ?LoggerInterface $logger;

    protected bool $debug = false;

    public function __construct(RWKVConfig $config, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
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
            'max_tokens' => $maxTokens,
        ];
        if ($stop) {
            $json['stop'] = $stop;
        }
        $this->debug && $this->logger?->debug(sprintf("Send: \nSystem Message: %s\nUser Message: %s", $messages['system'] ?? '', $messages['user'] ?? ''));
        $response = $this->client->post('/v1/chat/completions', [
            'json' => $json,
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
        $response = $this->client->post('/v1/completions', [
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
        $response = $this->client->get('/v1/models');
        return new ListResponse($response);
    }

    public function embedding(
        string $input,
        string $model = 'text-embedding-ada-002',
        ?string $user = null
    ): ListResponse {
        $json = [
            'input' => $input,
            'model' => $model,
        ];
        $user && $json['user'] = $user;
        $response = $this->client->post('/v1/embeddings', [
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

    protected function initConfig(RWKVConfig $config): static
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hyperf-Odin/1.0',
        ];
        $this->client = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'headers' => $headers,
        ]);
        $this->config = $config;
        return $this;
    }
}

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

namespace Hyperf\Odin\Api\Doubao;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\ClientInterface;
use Hyperf\Odin\Api\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Tool\ToolInterface;
use Hyperf\Odin\Utils\StreamUtil;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    protected GuzzleClient $client;

    protected DoubaoConfig $config;

    protected ?LoggerInterface $logger;

    protected bool $debug = false;

    public function __construct(DoubaoConfig $config, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->initConfig($config);
    }

    public function chat(
        array $messages,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 4096,
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
            'stream' => $stream,
            'model' => $model,
            'messages' => $messagesArr,
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
            }
        }
        if ($stop) {
            $json['stop'] = $stop;
        }
        $this->debug && $this->logger?->debug(sprintf("Send Messages: %s\nTools: %s", json_encode($messagesArr, JSON_UNESCAPED_UNICODE), json_encode($tools, JSON_UNESCAPED_UNICODE)));

        $options = [
            'json' => $json,
            'stream' => $stream,
        ];
        $url = '/api/v3/chat/completions';

        $resource = null;
        $response = null;
        if (StreamUtil::enabledContext($stream)) {
            $baseUri = $this->client->getConfig('base_uri');
            $options['headers'] = $this->client->getConfig('headers');
            $resource = StreamUtil::createContext('POST', $baseUri . $url, $options);
        } else {
            $response = $this->client->post($url, $options);
        }

        $chatCompletionResponse = new ChatCompletionResponse($response, $stream, $this->logger);
        $chatCompletionResponse->setResource($resource);
        $this->debug && $this->logger?->debug('Receive: ' . $chatCompletionResponse);
        return $chatCompletionResponse;
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

    protected function initConfig(DoubaoConfig $config): static
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Hyperf-Odin/1.0',
        ];
        if ($config->getApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $config->getApiKey();
        }
        $this->client = new GuzzleClient([
            'base_uri' => $config->getBaseUrl(),
            'headers' => $headers,
        ]);
        $this->config = $config;
        return $this;
    }
}

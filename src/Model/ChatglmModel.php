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

use Hyperf\Odin\Api\Chatglm\Chatglm;
use Hyperf\Odin\Api\Chatglm\ChatglmConfig;
use Hyperf\Odin\Api\Chatglm\Client as ChatglmClient;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\OpenAI\Response\ListResponse;

class ChatglmModel implements ModelInterface, EmbeddingInterface
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
        bool $stream = false,
    ): ChatCompletionResponse {
        $client = $this->getChatglmClient();
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $tools);
    }

    public function getChatglmClient(): ChatglmClient
    {
        $openAI = new Chatglm();
        $config = new ChatglmConfig($this->config['api_key'] ?? '');
        return $openAI->getClient($config, $this->model);
    }

    public function embedding(string $input): Embedding
    {
        $client = $this->getChatglmClient();
        /** @var ListResponse $response */
        $response = $client->embedding($input, $this->model);
        $embeddings = [];
        $data = $response->getData();
        if (isset($data[0])) {
            $embedding = $data[0];
            if ($embedding instanceof \Hyperf\Odin\Api\OpenAI\Response\Embedding) {
                $embeddings = $embedding->getEmbedding();
            }
        }
        return new Embedding($embeddings);
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    public function getVectorSize(): int
    {
        return 1536;
    }

}

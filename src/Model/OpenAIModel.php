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

use Hyperf\Odin\Api\OpenAI\Client;
use Hyperf\Odin\Api\OpenAI\OpenAI;
use Hyperf\Odin\Api\OpenAI\OpenAIConfig;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;

class OpenAIModel implements ModelInterface
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
    ): ChatCompletionResponse
    {
        $client = $this->getOpenAIClient();
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $tools);
    }

    protected function getOpenAIClient(): Client
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig($this->config['api_key'] ?? null, $this->config['organization'] ?? null, $this->config['base_url'] ?? 'https://api.openai.com/',);
        return $openAI->getClient($config, $this->model);
    }
}

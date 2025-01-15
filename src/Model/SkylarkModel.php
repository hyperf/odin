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

use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Skylark\Client;
use Hyperf\Odin\Api\Skylark\Skylark;
use Hyperf\Odin\Api\Skylark\SkylarkConfig;

class SkylarkModel implements ModelInterface
{
    public function __construct(public string $model, public array $config) {}

    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        bool $stream = false,
    ): ChatCompletionResponse {
        $client = $this->getSkylarkClient();
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $tools, $stream);
    }

    public function getSkylarkClient(): Client
    {
        $skylark = new Skylark();
        $config = new SkylarkConfig(
            apiKey: $this->config['api_key'] ?? null,
            baseUrl: $this->config['base_url'] ?? '',
            model: $this->config['model'] ?? '',
        );
        return $skylark->getClient($config);
    }
}

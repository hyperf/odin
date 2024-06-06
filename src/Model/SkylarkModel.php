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
use Hyperf\Odin\Message\AssistantMessage;

class SkylarkModel implements ModelInterface
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
        $client = $this->getSkylarkClient();
        return $client->chat($messages, $this->model, $temperature, $maxTokens, $stop, $tools);
    }

    public function getSkylarkClient(): Client
    {
        $skylark = new Skylark();
        $config = new SkylarkConfig(ak: $this->config['ak'], sk: $this->config['sk'], endpoint: $this->config['endpoint'], host: $this->config['host'], region: $this->config['region'], service: $this->config['service']);
        return $skylark->getClient($config);
    }

    public function parseAnswer(AssistantMessage $message): AssistantMessage
    {
        $content = $message->getContent();
        // 如果存在 <|Answer|> 标记，则取出 <|Answer|>: 后面的内容作为回答
        if (str_contains($content, '<|Answer|>:')) {
            $answer = substr($content, strpos($content, '<|Answer|>:') + 11);
            $message->setContent($answer);
        }
        return $message;
    }
}

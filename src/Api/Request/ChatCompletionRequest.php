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

namespace Hyperf\Odin\Api\Request;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Contract\Api\Request\RequestInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Utils\MessageUtil;
use Hyperf\Odin\Utils\ToolUtil;

class ChatCompletionRequest implements RequestInterface
{
    private ?array $filterMessages = null;

    private bool $streamContentEnabled = false;

    private float $frequencyPenalty = 0.0;

    private float $presencePenalty = 0.0;

    private array $businessParams = [];

    private bool $toolsCache = false;

    public function __construct(
        /** @var MessageInterface[] $messages */
        protected array $messages,
        protected string $model,
        protected float $temperature = 0.5,
        protected int $maxTokens = 0,
        protected array $stop = [],
        protected array $tools = [],
        protected bool $stream = false,
    ) {}

    public function validate(): void
    {
        if (empty($this->model)) {
            throw new InvalidArgumentException('Model is required.');
        }
        // 温度只能在 [0,1]
        if ($this->temperature < 0 || $this->temperature > 1) {
            throw new InvalidArgumentException('Temperature must be between 0 and 1.');
        }
        $this->filterMessages = MessageUtil::filter($this->messages);
        if (empty($this->filterMessages)) {
            throw new InvalidArgumentException('Messages is required.');
        }
    }

    public function createOptions(): array
    {
        $json = [
            'messages' => $this->filterMessages ?? MessageUtil::filter($this->messages),
            'model' => $this->model,
            'temperature' => $this->temperature,
            'stream' => $this->stream,
        ];
        if ($this->maxTokens > 0) {
            $json['max_completion_tokens'] = $this->maxTokens;
        }
        if (! empty($this->stop)) {
            $json['stop'] = $this->stop;
        }
        $tools = ToolUtil::filter($this->tools);
        if (! empty($tools)) {
            $json['tools'] = $tools;
            $json['tool_choice'] = 'auto';
        }
        if ($this->frequencyPenalty > 0) {
            $json['frequency_penalty'] = $this->frequencyPenalty;
        }
        if ($this->presencePenalty > 0) {
            $json['presence_penalty'] = $this->presencePenalty;
        }
        if (! empty($this->businessParams)) {
            $json['business_params'] = $this->businessParams;
        }

        return [
            RequestOptions::JSON => $json,
            RequestOptions::STREAM => $this->stream,
        ];
    }

    public function setFrequencyPenalty(float $frequencyPenalty): void
    {
        $this->frequencyPenalty = $frequencyPenalty;
    }

    public function setPresencePenalty(float $presencePenalty): void
    {
        $this->presencePenalty = $presencePenalty;
    }

    public function setBusinessParams(array $businessParams): void
    {
        $this->businessParams = $businessParams;
    }

    public function setStream(bool $stream): void
    {
        $this->stream = $stream;
    }

    public function isStream(): bool
    {
        return $this->stream;
    }

    public function isStreamContentEnabled(): bool
    {
        return $this->streamContentEnabled;
    }

    public function setStreamContentEnabled(bool $streamContentEnabled): void
    {
        $this->streamContentEnabled = $streamContentEnabled;
    }

    /**
     * 获取消息列表.
     *
     * @return array<MessageInterface> 消息列表
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * 获取工具列表.
     *
     * @return array 工具列表
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * 获取模型名称.
     *
     * @return string 模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function getStop(): array
    {
        return $this->stop;
    }

    public function isToolsCache(): bool
    {
        return $this->toolsCache;
    }

    public function setToolsCache(bool $toolsCache): void
    {
        $this->toolsCache = $toolsCache;
    }
}

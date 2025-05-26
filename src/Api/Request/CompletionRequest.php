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
use Hyperf\Odin\Exception\InvalidArgumentException;

class CompletionRequest implements RequestInterface
{
    private float $frequencyPenalty = 0.0;

    private float $presencePenalty = 0.0;

    private array $businessParams = [];

    private bool $includeBusinessParams = false;

    public function __construct(
        protected string $model,
        protected string $prompt,
        protected float $temperature = 0.5,
        protected int $maxTokens = 0,
        protected array $stop = [],
    ) {}

    public function validate(): void
    {
        if (empty($this->model)) {
            throw new InvalidArgumentException('Model is required.');
        }
        if ($this->prompt === '') {
            throw new InvalidArgumentException('Prompt is required.');
        }
        // 温度只能在 [0,1]
        if ($this->temperature < 0 || $this->temperature > 1) {
            throw new InvalidArgumentException('Temperature must be between 0 and 1.');
        }
    }

    public function createOptions(): array
    {
        $json = [
            'model' => $this->model,
            'prompt' => $this->prompt,
            'temperature' => $this->temperature,
        ];
        if ($this->maxTokens > 0) {
            $json['max_tokens'] = $this->maxTokens;
        }
        if (! empty($this->stop)) {
            $json['stop'] = $this->stop;
        }
        if ($this->frequencyPenalty > 0) {
            $json['frequency_penalty'] = $this->frequencyPenalty;
        }
        if ($this->presencePenalty > 0) {
            $json['presence_penalty'] = $this->presencePenalty;
        }
        if ($this->includeBusinessParams && ! empty($this->businessParams)) {
            $json['business_params'] = $this->businessParams;
        }

        return [
            RequestOptions::JSON => $json,
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

    public function setIncludeBusinessParams(bool $includeBusinessParams): void
    {
        $this->includeBusinessParams = $includeBusinessParams;
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
}

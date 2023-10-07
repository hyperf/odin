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

namespace Hyperf\Odin\Conversation;

class Option
{
    protected float $temperature;

    protected int $maxTokens;

    protected array $stop;

    protected array $functions;

    public function __construct(float $temperature = 0, int $maxTokens = 1000, array $stop = [], array $functions = [])
    {
        $this->setTemperature($temperature);
        $this->setMaxTokens($maxTokens);
        $this->setStop($stop);
        $this->setFunctions($functions);
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function setMaxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function getStop(): array
    {
        return $this->stop;
    }

    public function setStop(array $stop): static
    {
        $this->stop = $stop;
        return $this;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    public function setFunctions(array $functions): static
    {
        $this->functions = $functions;
        return $this;
    }
}

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

namespace Hyperf\Odin\Apis\OpenAI\Response;

use Hyperf\Contract\Arrayable;

class FunctionCall implements Arrayable
{

    protected string $originalName;
    protected string $originalArguments;

    /**
     * @param string $name
     * @param array $arguments
     * @param bool $shouldFix Sometimes the API will return a wrong function call. If this flag is true will attempt to fix that.
     */
    public function __construct(protected string $name, protected array $arguments, protected bool $shouldFix = false)
    {
    }

    public static function fromArray(array $functionCall): ?static
    {
        if (isset($functionCall['arguments'])) {
            $arguments = json_decode($functionCall['arguments'], true);
        } else {
            return null;
        }
        $shouldFix = false;
        if (! is_array($arguments)) {
            $arguments = [];
            $shouldFix = true;
        }
        $static = new static($functionCall['name'] ?? '', $arguments);
        $static->setOriginalName($functionCall['name'] ?? '');
        $static->setOriginalArguments($functionCall['arguments'] ?? '');
        $shouldFix && $static->setShouldFix(true);
        return $static;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function isShouldFix(): bool
    {
        return $this->shouldFix;
    }

    public function setShouldFix(bool $shouldFix): static
    {
        $this->shouldFix = $shouldFix;
        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getOriginalArguments(): string
    {
        return $this->originalArguments;
    }

    public function setOriginalArguments(string $originalArguments): static
    {
        $this->originalArguments = $originalArguments;
        return $this;
    }
}

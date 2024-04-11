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

namespace Hyperf\Odin\Api\OpenAI\Request;

use Hyperf\Contract\Arrayable;
use InvalidArgumentException;

class ToolDefinition implements Arrayable
{
    protected string $name;

    protected string $description;

    protected ?ToolParameters $parameters;

    /**
     * @var callable[]
     */
    protected array $toolHandler = [];
    protected array $examples;

    public function __construct(
        string $name,
        string $description = '',
        ?ToolParameters $parameters = null,
        array $examples = [],
        callable|array $toolHandler = [],
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->examples = $examples;
        $this->setToolHandler($toolHandler);
    }

    public function toArray(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters()?->toArray(),
            ]
        ];
    }

    public function toArrayWithExamples(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
                'parameters' => $this->getParameters()?->toArray(),
                'examples' => $this->getExamples(),
            ]
        ];
    }

    public function getToolHandler(): array
    {
        return $this->toolHandler;
    }

    public function setToolHandler(array|callable $toolHandler): static
    {
        if (! is_callable($toolHandler)) {
            throw new InvalidArgumentException('Tool handler must be callable.');
        }
        $this->toolHandler = $toolHandler;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    public function getParameters(): ?ToolParameters
    {
        return $this->parameters;
    }

    public function setParameters(ToolParameters $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getExamples(): array
    {
        return $this->examples;
    }

    public function setExamples(array $examples)
    {
        $this->examples = $examples;
        return $this;
    }
}

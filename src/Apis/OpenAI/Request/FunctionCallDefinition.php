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

namespace Hyperf\Odin\Apis\OpenAI\Request;

use Hyperf\Contract\Arrayable;
use InvalidArgumentException;

class FunctionCallDefinition implements Arrayable
{
    protected string $name;

    protected string $description;

    protected ?FunctionCallParameters $parameters;

    /**
     * @var callable[]
     */
    protected array $functionCallHandlers = [];

    public function __construct(
        string $name,
        string $description = '',
        ?FunctionCallParameters $parameters = null,
        callable|array $functionHandlers = []
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->setFunctionCallHandlers($functionHandlers);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'parameters' => $this->getParameters()?->toArray(),
        ];
    }

    public function getFunctionCallHandlers(): array
    {
        return $this->functionCallHandlers;
    }

    public function setFunctionCallHandlers(array|callable $functionCallHandlers): static
    {
        if (! is_array($functionCallHandlers)) {
            $functionCallHandlers = [$functionCallHandlers];
        }
        foreach ($functionCallHandlers as $functionCallHandler) {
            if (! is_callable($functionCallHandler)) {
                throw new InvalidArgumentException('Function call handler must be callable.');
            }
        }
        $this->functionCallHandlers = $functionCallHandlers;
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

    public function getParameters(): ?FunctionCallParameters
    {
        return $this->parameters;
    }

    public function setParameters(FunctionCallParameters $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }
}

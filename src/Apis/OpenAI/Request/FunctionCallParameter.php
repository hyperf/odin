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

class FunctionCallParameter
{
    protected string $name;

    protected string $description;

    protected string $type;

    protected ?array $enum;

    protected bool $required;

    public function __construct(
        string $name,
        string $description,
        string $type = 'string',
        bool $required = true,
        ?array $enum = null
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->type = $type;
        $this->required = $required;
        $this->enum = $enum;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function setEnum(array $enum): static
    {
        $this->enum = $enum;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;
        return $this;
    }
}

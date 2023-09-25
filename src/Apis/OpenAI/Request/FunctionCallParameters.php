<?php

namespace Hyperf\Odin\Apis\OpenAI\Request;


class FunctionCallParameters
{

    protected string $type = 'object';
    protected array $properties = [];
    protected array $required = [];

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    public function getRequired(): array
    {
        return $this->required;
    }

    public function setRequired(array $required): void
    {
        $this->required = $required;
    }

}
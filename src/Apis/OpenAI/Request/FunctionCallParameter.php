<?php

namespace Hyperf\Odin\Apis\OpenAI\Request;


class FunctionCallParameter
{

    protected string $type;
    protected string $description = '';
    protected array $enum = [];

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getEnum(): array
    {
        return $this->enum;
    }

    public function setEnum(array $enum): void
    {
        $this->enum = $enum;
    }

}
<?php

namespace Hyperf\Odin\Tools;


use Hyperf\Odin\Apis\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Apis\OpenAI\Request\ToolParameters;

abstract class AbstractTool implements ToolInterface
{

    public string $name = '';
    public string $description = '';

    public array $parameters = [];


    public function toToolDefinition(): ToolDefinition
    {
        return new ToolDefinition($this->name, $this->description, ToolParameters::fromArray($this->parameters) ?? null, [
            $this,
            'invoke'
        ]);
    }

}
<?php

namespace Hyperf\Odin\Apis\OpenAI\Request;

class FunctionCallDefinition
{

    protected string $name;
    protected ?string $description;
    protected ?FunctionCallParameters $parameters;
}
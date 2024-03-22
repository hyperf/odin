<?php

namespace Hyperf\Odin\Tools;

use Hyperf\Odin\Apis\OpenAI\Request\ToolDefinition;

interface ToolInterface
{

    public function toToolDefinition(): ToolDefinition;

}
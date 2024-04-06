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

namespace Hyperf\Odin\Tools;

use Hyperf\Odin\Apis\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Apis\OpenAI\Request\ToolParameters;

abstract class AbstractTool implements ToolInterface
{
    public string $name = '';

    public string $description = '';

    public array $parameters = [];

    public array $examples = [];

    public function toToolDefinition(): ToolDefinition
    {
        return new ToolDefinition($this->name, $this->description, ToolParameters::fromArray($this->parameters) ?? null, $this->examples, [
            $this,
            'invoke',
        ]);
    }
}

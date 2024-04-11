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

namespace Hyperf\Odin\Tool;

use Hyperf\Odin\Api\OpenAI\Request\ToolDefinition;

interface ToolInterface
{
    public function toToolDefinition(): ToolDefinition;
}

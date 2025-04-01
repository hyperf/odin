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

namespace Hyperf\Odin\Contract\Tool;

use Hyperf\Odin\Tool\Definition\ToolDefinition;

/**
 * 工具接口.
 */
interface ToolInterface
{
    /**
     * 获取工具定义.
     */
    public function toToolDefinition(): ToolDefinition;
}

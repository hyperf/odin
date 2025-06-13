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

namespace Hyperf\Odin\Contract\Mcp;

use Hyperf\Odin\Tool\Definition\ToolDefinition;

interface McpServerManagerInterface
{
    public function discover(): void;

    /**
     * @return array<ToolDefinition>
     */
    public function getAllTools(): array;

    public function callMcpTool(string $toolName, array $args = []): array;
}

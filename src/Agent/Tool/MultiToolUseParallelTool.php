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

namespace Hyperf\Odin\Agent\Tool;

use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Throwable;

class MultiToolUseParallelTool extends ToolDefinition
{
    /**
     * @var array<string, ToolDefinition>
     */
    private array $allTools;

    public function __construct(array $allTools = [])
    {
        $this->allTools = $allTools;
        parent::__construct(
            name: 'multi_tool_use.parallel',
            toolHandler: [$this, 'execute']
        );
    }

    public function execute($args): array
    {
        $toolUses = $args['tool_uses'] ?? [];
        if (empty($toolUses)) {
            return [];
        }
        $results = [];
        $toolExecutor = new ToolExecutor();
        foreach ($toolUses as $toolUse) {
            $recipientName = $toolUse['recipient_name'] ?? '';
            // 提取 function 名
            $functionName = explode('.', $recipientName)[1] ?? '';
            // 入参
            $parameters = $toolUse['parameters'] ?? [];

            $tool = $this->allTools[$functionName] ?? null;
            if (! $tool) {
                continue;
            }
            $toolExecutor->add(function () use ($recipientName, $tool, $parameters, &$results) {
                $success = true;
                try {
                    $callToolResult = call_user_func($tool->getToolHandler(), $parameters);
                } catch (Throwable $throwable) {
                    $success = false;
                    $callToolResult = ['error' => $throwable->getMessage()];
                }
                $results[] = [
                    'recipient_name' => $recipientName,
                    'success' => $success,
                    'result' => $callToolResult,
                ];
            });
        }
        $toolExecutor->run();
        return $results;
    }
}

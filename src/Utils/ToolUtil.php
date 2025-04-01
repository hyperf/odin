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

namespace Hyperf\Odin\Utils;

use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

class ToolUtil
{
    public static function filter(array $tools): array
    {
        $toolsArray = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $toolsArray[] = $tool->toToolDefinition()->toFunctionCall();
            } elseif ($tool instanceof ToolDefinition) {
                $toolsArray[] = $tool->toFunctionCall();
            } else {
                $toolsArray[] = $tool;
            }
        }
        return $toolsArray;
    }

    public static function createFromArray(array $toolArray): ?ToolDefinition
    {
        if (isset($toolArray['function'])) {
            $toolArray = $toolArray['function'];
        }
        $name = $toolArray['name'] ?? '';
        $description = $toolArray['description'] ?? '';
        $parameters = $toolArray['parameters'] ?? [];
        if (empty($name)) {
            return null;
        }
        $toolHandler = $toolArray['toolHandler'] ?? function (...$args) {
            // 仅定义
            return '';
        };
        return new ToolDefinition(
            name: $name,
            description: $description,
            parameters: ToolParameters::fromArray($parameters),
            toolHandler: $toolHandler
        );
    }
}

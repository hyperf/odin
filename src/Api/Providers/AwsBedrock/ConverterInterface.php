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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;

interface ConverterInterface
{
    public function convertSystemMessage(SystemMessage $message): array|string;

    public function convertToolMessage(ToolMessage $message): array;

    public function convertAssistantMessage(AssistantMessage $message): array;

    public function convertUserMessage(UserMessage $message): array;

    /**
     * @param array<ToolDefinition> $tools
     */
    public function convertTools(array $tools): array;
}

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

namespace Hyperf\Odin\Prompt;

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

interface PromptInterface
{
    public function toArray(): array;

    public function getSystemPrompt(string $agentScratchpad = ''): SystemMessage;

    public function getUserPrompt(string $input): UserMessage;
}

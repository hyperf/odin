<?php

namespace Hyperf\Odin\Prompt;

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

interface PromptInterface
{

    public function toArray(): array;

    public function getSystemPrompt(string $agentScratchpad = ''): SystemMessage;

    public function getUserPrompt(string $input): UserMessage;

}
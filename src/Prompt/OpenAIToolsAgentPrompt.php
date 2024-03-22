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

class OpenAIToolsAgentPrompt implements PromptInterface
{
    public string $systemPrompt
        = <<<'PROMPT'
You are a helpful and polite and friendly AI assistant, your first language is Simplified Chinese, your answer should be simple and clear, your task is to help users solve problems, and you can also ask questions to understand the user's needs better
PROMPT;

    public string $userPrompt
        = <<<'PROMPT'
{input}
PROMPT;

    public string $placeholders
        = <<<'PROMPT'
{agent_scratchpad}
PROMPT;

    public function __construct(?string $systemPrompt = null, ?string $userPrompt = null, ?string $placeholders = null)
    {
        if (! is_null($systemPrompt)) {
            $this->systemPrompt = $systemPrompt;
        }
        if (! is_null($userPrompt)) {
            $this->userPrompt = $userPrompt;
        }
        if (! is_null($placeholders)) {
            $this->placeholders = $placeholders;
        }
        $this->systemPrompt .= "\n" . $this->placeholders;
    }

    public function toArray(): array
    {
        return [
            'system' => new SystemMessage($this->systemPrompt),
            'user' => new UserMessage($this->userPrompt),
        ];
    }

    public function getSystemPrompt(string $agentScratchpad = ''): SystemMessage
    {
        return new SystemMessage(str_replace('{agent_scratchpad}', $agentScratchpad, $this->systemPrompt));
    }

    public function getUserPrompt(string $input): UserMessage
    {
        return new UserMessage(str_replace('{input}', $input, $this->userPrompt));
    }

}

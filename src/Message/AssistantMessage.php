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

namespace Hyperf\Odin\Message;

use Hyperf\Odin\Api\OpenAI\Response\ToolCall;

class AssistantMessage extends AbstractMessage
{
    protected Role $role = Role::Assistant;

    /**
     * @var ToolCall[]
     */
    protected array $toolCalls = [];

    protected ?string $reasoningContent = null;

    public function __construct(string $content, array $toolsCall = [], ?string $reasoningContent = null)
    {
        parent::__construct($content);
        $this->toolCalls = $toolsCall;
        $this->reasoningContent = $reasoningContent;
    }

    public static function fromArray(array $message): static
    {
        return new static($message['content'] ?? '', ToolCall::fromArray($message['tool_calls'] ?? []), $message['reasoning_content'] ?? null);
    }

    public function toArray(): array
    {
        $toolCalls = [];
        foreach ($this->toolCalls as $toolCall) {
            $toolCalls[] = $toolCall->toArray();
        }
        $result = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
        if (! is_null($this->reasoningContent)) {
            $result['reasoning_content'] = $this->reasoningContent;
        }
        $toolCalls && $result['tool_calls'] = $toolCalls;
        return $result;
    }

    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function setToolCalls(array $toolCalls): static
    {
        $this->toolCalls = $toolCalls;
        return $this;
    }

    public function getReasoningContent(): ?string
    {
        return $this->reasoningContent;
    }

    public function hasReasoningContent(): bool
    {
        return ! is_null($this->reasoningContent);
    }
}

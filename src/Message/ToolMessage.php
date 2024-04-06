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

class ToolMessage extends AbstractMessage
{
    protected Role $role = Role::Tool;

    protected string $toolCallId = '';

    public function __construct(string $content, string $toolCallId)
    {
        $this->content = $content;
        $this->toolCallId = $toolCallId;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->getContent(),
            'tool_call_id' => $this->getToolCallId(),
        ];
    }

    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    public function setToolCallId(string $toolCallId): static
    {
        $this->toolCallId = $toolCallId;
        return $this;
    }
}

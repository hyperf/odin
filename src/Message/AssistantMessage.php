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

    public function __construct(string $content, array $toolsCall = [])
    {
        parent::__construct($content);
        $this->toolCalls = $toolsCall;
    }

    public static function fromArray(array $message): static
    {
        return new static($message['content'] ?? '', ToolCall::fromArray($message['tool_calls'] ?? []));
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
}

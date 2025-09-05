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

/**
 * 工具消息类.
 *
 * 用于表示工具调用的消息，包括函数调用响应
 */
class ToolMessage extends AbstractMessage
{
    /**
     * 默认角色为工具.
     */
    protected Role $role = Role::Tool;

    /**
     * 工具调用ID.
     */
    protected string $toolCallId = '';

    /**
     * 工具名称.
     */
    protected ?string $name = null;

    /**
     * 工具调用参数.
     */
    protected ?array $arguments = null;

    /**
     * 构造函数.
     *
     * @param string $content 工具调用结果内容
     * @param string $toolCallId 工具调用ID
     * @param null|string $name 工具名称
     * @param null|array $arguments 工具调用参数
     */
    public function __construct(string $content, string $toolCallId, ?string $name = null, ?array $arguments = null)
    {
        parent::__construct($content);
        $this->toolCallId = $this->normalizeToolCallId($toolCallId);
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /**
     * 转换为数组.
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->getContent(),
            'tool_call_id' => $this->getToolCallId(),
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->arguments !== null) {
            $data['arguments'] = $this->arguments;
        }

        return $data;
    }

    /**
     * 获取工具调用ID.
     */
    public function getToolCallId(): string
    {
        return $this->toolCallId;
    }

    /**
     * 设置工具调用ID.
     */
    public function setToolCallId(string $toolCallId): self
    {
        $this->toolCallId = $toolCallId;
        return $this;
    }

    /**
     * 获取工具名称.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * 设置工具名称.
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取工具调用参数.
     */
    public function getArguments(): ?array
    {
        return $this->arguments;
    }

    /**
     * 设置工具调用参数.
     */
    public function setArguments(?array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * 创建一个函数消息实例.
     *
     * @param string $content 函数调用结果内容
     * @param string $toolCallId 函数调用ID
     * @param null|string $name 函数名称
     * @param null|array $arguments 函数调用参数
     */
    public static function function(string $content, string $toolCallId, ?string $name = null, ?array $arguments = null): self
    {
        $instance = new self($content, $toolCallId, $name, $arguments);
        $instance->role = Role::Tool;
        return $instance;
    }

    /**
     * 从数组创建消息实例.
     *
     * @param array $message 消息数组
     * @return static 消息实例
     */
    public static function fromArray(array $message): self
    {
        $content = $message['content'] ?? '';
        $toolCallId = $message['tool_call_id'] ?? '';
        $name = $message['name'] ?? null;
        $arguments = $message['arguments'] ?? null;

        return match ($message['role'] ?? '') {
            Role::Tool->value => static::function($content, $toolCallId, $name, $arguments),
            default => new self($content, $toolCallId, $name, $arguments),
        };
    }
}

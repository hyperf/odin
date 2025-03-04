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

use Hyperf\Odin\Api\Response\ToolCall;

/**
 * 助手消息类.
 *
 * 用于表示AI助手的回复，可包含内容、工具调用和推理过程
 */
class AssistantMessage extends AbstractMessage
{
    /**
     * 角色固定为助手.
     */
    protected Role $role = Role::Assistant;

    /**
     * 工具调用列表.
     *
     * @var ToolCall[]
     */
    protected array $toolCalls = [];

    /**
     * 推理内容
     * 用于表示LLM的推理过程，非输出内容的一部分.
     */
    protected ?string $reasoningContent = null;

    /**
     * 构造函数.
     *
     * @param string $content 消息内容
     * @param array<ToolCall> $toolsCall 工具调用列表
     * @param null|string $reasoningContent 推理内容
     */
    public function __construct(string $content, array $toolsCall = [], ?string $reasoningContent = null)
    {
        parent::__construct($content);
        $this->toolCalls = $toolsCall;
        $this->reasoningContent = $reasoningContent;
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
        $toolCalls = ToolCall::fromArray($message['tool_calls'] ?? []);
        $reasoningContent = $message['reasoning_content'] ?? null;

        return new self($content, $toolCalls, $reasoningContent);
    }

    /**
     * 转换为数组.
     *
     * @return array 消息数组表示
     */
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
        if (! empty($toolCalls)) {
            $result['tool_calls'] = $toolCalls;
        }
        return $result;
    }

    /**
     * 获取消息内容.
     *
     * @return string 消息内容文本
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * 是否有工具调用.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * 获取工具调用列表.
     *
     * @return array<ToolCall> 工具调用列表
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * 设置工具调用列表.
     *
     * @param array $toolCalls 工具调用列表
     * @return static 支持链式调用
     */
    public function setToolCalls(array $toolCalls): self
    {
        $this->toolCalls = $toolCalls;
        return $this;
    }

    /**
     * 获取推理内容.
     *
     * @return null|string 推理内容
     */
    public function getReasoningContent(): ?string
    {
        return $this->reasoningContent;
    }

    /**
     * 是否有推理内容.
     */
    public function hasReasoningContent(): bool
    {
        return ! is_null($this->reasoningContent);
    }

    /**
     * 设置推理内容.
     *
     * @param null|string $reasoningContent 推理内容
     * @return static 支持链式调用
     */
    public function setReasoningContent(?string $reasoningContent): self
    {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }
}

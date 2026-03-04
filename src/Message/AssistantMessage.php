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
 * content 支持 OpenAI 最新格式：string 或 array of ContentPart (text/refusal)
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
     * content 的 array 格式（OpenAI API）
     * 当非 null 时，toArray 输出 array 格式；getContent 从其中提取文本.
     *
     * @var null|array<array{type: string, text?: string, refusal?: string}>
     */
    protected ?array $contentParts = null;

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
        $this->toolCalls = $this->normalizeToolCallIds($toolsCall);
        $this->reasoningContent = $reasoningContent;
    }

    /**
     * 从数组创建消息实例.
     *
     * @param array $message 消息数组，content 可为 string 或 array（OpenAI 格式）
     * @return static 消息实例
     */
    public static function fromArray(array $message): self
    {
        $content = $message['content'] ?? '';
        $toolCalls = ToolCall::fromArray($message['tool_calls'] ?? []);
        $reasoningContent = $message['reasoning_content'] ?? null;

        $contentParts = null;
        if (is_array($content) && ! empty($content)) {
            $contentParts = $content;
            $contentString = self::extractTextFromContentParts($content);
        } else {
            $contentString = is_string($content) ? $content : '';
        }

        $instance = new self($contentString, $toolCalls, $reasoningContent);
        if ($contentParts !== null) {
            $instance->contentParts = $contentParts;
        }
        return $instance;
    }

    /**
     * 转换为数组.
     *
     * @return array 消息数组表示，content 保持 OpenAI 格式（string 或 array）
     */
    public function toArray(): array
    {
        $toolCalls = [];
        foreach ($this->toolCalls as $toolCall) {
            $toolCalls[] = $toolCall->toArray();
        }
        $content = $this->contentParts !== null ? $this->contentParts : $this->content;
        $result = [
            'role' => $this->role->value,
            'content' => $content,
        ];
        if (! is_null($this->reasoningContent)) {
            $result['reasoning_content'] = $this->reasoningContent;
        }
        if (! empty($toolCalls)) {
            $result['tool_calls'] = $toolCalls;
        }
        return $result;
    }

    public function toArrayWithStream(): array
    {
        $toolCalls = [];
        foreach ($this->toolCalls as $toolCall) {
            $toolCalls[] = $toolCall->toArrayWithStream();
        }
        $content = $this->contentParts !== null ? $this->contentParts : $this->content;
        $result = [
            'role' => $this->role->value,
            'content' => $content,
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
     * 获取消息内容（字符串形式）.
     *
     * @return string 消息内容文本
     */
    public function getContent(): string
    {
        if ($this->contentParts !== null) {
            return self::extractTextFromContentParts($this->contentParts);
        }
        return $this->content;
    }

    /**
     * 设置消息内容为字符串时，清空 contentParts 以保持一致性.
     */
    public function setContent(string $content): self
    {
        $this->contentParts = null;
        parent::setContent($content);
        return $this;
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

    /**
     * 从 content parts array 提取文本.
     *
     * @param array<array{type?: string, text?: string, refusal?: string}> $contentParts
     */
    private static function extractTextFromContentParts(array $contentParts): string
    {
        $parts = [];
        foreach ($contentParts as $part) {
            if (! is_array($part)) {
                continue;
            }
            $type = $part['type'] ?? null;
            if ($type === 'text' && isset($part['text'])) {
                $parts[] = $part['text'];
            } elseif ($type === 'refusal' && isset($part['refusal'])) {
                $parts[] = $part['refusal'];
            }
        }
        return implode('', $parts);
    }

    /**
     * 标准化 tool call IDs 以确保跨平台兼容性.
     *
     * @param array<ToolCall> $toolCalls 原始工具调用列表
     * @return array<ToolCall> 标准化后的工具调用列表
     */
    private function normalizeToolCallIds(array $toolCalls): array
    {
        foreach ($toolCalls as $toolCall) {
            $originalId = $toolCall->getId();
            $normalizedId = $this->normalizeToolCallId($originalId);

            if ($normalizedId !== $originalId) {
                $toolCall->setId($normalizedId);
            }
        }

        return $toolCalls;
    }
}

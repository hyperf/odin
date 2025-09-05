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

use Hyperf\Odin\Contract\Message\MessageInterface;
use Stringable;

abstract class AbstractMessage implements MessageInterface, Stringable
{
    protected Role $role;

    protected string $content = '';

    protected array $context = [];

    /**
     * 消息唯一标识
     * 非必须，默认为空字符串.
     */
    protected string $identifier = '';

    /**
     * @var array 业务参数
     */
    protected array $params = [];

    /**
     * 由于每种模型服务商的缓存点不同，所以最终的请求参数将会在 Client 中实现，这里仅是定义.
     * @var null|CachePoint 消息缓存点
     */
    protected ?CachePoint $cachePoint = null;

    /**
     * 估算的 token 数量.
     */
    protected ?int $tokenEstimate = null;

    public function __construct(string $content, array $context = [])
    {
        $this->content = $content;
        $this->context = $context;
    }

    public function __toString(): string
    {
        // Replace the variables in content according to the key in context, for example {name} matches $context['name']
        $content = $this->content;
        foreach ($this->context as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    public function formatContent(array $context): string
    {
        $context = array_merge($this->context, $context);
        $content = $this->content;
        foreach ($context as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    /**
     * 获取消息角色.
     *
     * @return Role 消息角色
     */
    public function getRole(): Role
    {
        return $this->role;
    }

    /**
     * 获取消息唯一标识
     * 非必须，默认为空字符串.
     *
     * @return string 消息唯一标识
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * 设置消息唯一标识
     * 非必须，默认为空字符串.
     *
     * @param string $identifier 唯一标识
     * @return self 支持链式调用
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
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
     * 设置消息内容文本.
     *
     * @param string $content 消息内容文本
     * @return static 支持链式调用
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function appendContent(string $content): self
    {
        $this->content .= $content;
        return $this;
    }

    public function getContext(string $key): mixed
    {
        return $this->context[$key] ?? null;
    }

    public function setContext(string $key, $value): mixed
    {
        $this->context[$key] = $value;
        return $value;
    }

    public function hasContext(string $key): bool
    {
        return isset($this->context[$key]);
    }

    public function getCachePoint(): ?CachePoint
    {
        return $this->cachePoint;
    }

    public function setCachePoint(?CachePoint $cachePoint): self
    {
        $this->cachePoint = $cachePoint;
        return $this;
    }

    public function getTokenEstimate(): ?int
    {
        return $this->tokenEstimate;
    }

    public function setTokenEstimate(?int $tokenEstimate): self
    {
        $this->tokenEstimate = $tokenEstimate;
        return $this;
    }

    public function getHash(): string
    {
        return md5(serialize($this->toArray()));
    }

    /**
     * 标准化 tool call ID 以确保跨平台兼容性.
     *
     * 将包含不兼容字符（如冒号）的 tool call ID 转换为 MD5 格式
     * 解决 kimi-k2 等模型与 AWS Claude 的兼容性问题
     *
     * @param string $toolCallId 原始工具调用ID
     * @return string 标准化后的工具调用ID
     */
    protected function normalizeToolCallId(string $toolCallId): string
    {
        // 检查 ID 是否包含不兼容字符（AWS 要求：只允许 [a-zA-Z0-9_-]）
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $toolCallId)) {
            // 使用 MD5 生成兼容的 ID
            return md5($toolCallId);
        }

        return $toolCallId;
    }
}

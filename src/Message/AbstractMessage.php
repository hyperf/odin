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
}

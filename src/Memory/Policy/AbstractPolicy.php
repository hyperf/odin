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

namespace Hyperf\Odin\Memory\Policy;

use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Message\AbstractMessage;

/**
 * 抽象记忆策略类.
 *
 * 提供基本的策略功能和配置管理
 */
abstract class AbstractPolicy implements PolicyInterface
{
    /**
     * 配置选项.
     */
    protected array $options = [];

    /**
     * 构造函数.
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * 配置策略参数.
     *
     * @param array $options 配置选项
     * @return self 支持链式调用
     */
    public function configure(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * 处理消息列表，返回经过策略处理后的消息列表.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    abstract public function process(array $messages): array;

    /**
     * 获取默认配置选项.
     *
     * @return array 默认配置选项
     */
    protected function getDefaultOptions(): array
    {
        return [];
    }

    /**
     * 获取配置参数.
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    protected function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}

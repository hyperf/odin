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

namespace Hyperf\Odin\Memory\Driver;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;

/**
 * 内存记忆驱动.
 *
 * 提供基于内存的消息存储和检索
 */
class InMemoryDriver implements DriverInterface
{
    /**
     * 普通消息存储.
     *
     * @var MessageInterface[]
     */
    protected array $messages = [];

    /**
     * 系统消息存储.
     *
     * @var MessageInterface[]
     */
    protected array $systemMessages = [];

    /**
     * 配置选项.
     */
    protected array $config = [
        'max_messages' => 200, // 默认最大消息数量
    ];

    /**
     * 构造函数.
     *
     * @param array $config 配置选项
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 添加消息到记忆上下文.
     *
     * @param MessageInterface $message 要添加的消息对象
     */
    public function addMessage(MessageInterface $message): void
    {
        // 确保消息数量不超过限制
        if (count($this->messages) >= $this->config['max_messages']) {
            // 移除最早的消息
            array_shift($this->messages);
        }

        $this->messages[] = $message;
    }

    /**
     * 添加系统消息到记忆上下文.
     *
     * @param MessageInterface $message 要添加的系统消息对象
     */
    public function addSystemMessage(MessageInterface $message): void
    {
        // 系统消息不受数量限制，因为通常很少
        $this->systemMessages[] = $message;
    }

    /**
     * 获取所有普通消息.
     *
     * @return MessageInterface[] 所有普通消息列表
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * 获取所有系统消息.
     *
     * @return MessageInterface[] 所有系统消息列表
     */
    public function getSystemMessages(): array
    {
        return $this->systemMessages;
    }

    /**
     * 清空所有消息.
     */
    public function clear(): void
    {
        $this->messages = [];
        $this->systemMessages = [];
    }

    /**
     * 获取配置参数.
     *
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 设置配置参数.
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return self 支持链式调用
     */
    public function setConfig(string $key, mixed $value): self
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * 设置多个配置参数.
     *
     * @param array $config 配置数组
     * @return self 支持链式调用
     */
    public function setConfigs(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}

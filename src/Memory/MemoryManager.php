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

namespace Hyperf\Odin\Memory;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Contract\Memory\MemoryInterface;
use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Memory\Driver\InMemoryDriver;

/**
 * 记忆管理器.
 *
 * 管理整体记忆流程，协调驱动和策略的交互
 */
class MemoryManager implements MemoryInterface
{
    /**
     * 记忆驱动.
     */
    protected DriverInterface $driver;

    /**
     * 记忆策略.
     */
    protected ?PolicyInterface $policy = null;

    /**
     * 处理后的消息缓存.
     */
    protected ?array $processedCache = null;

    /**
     * 构造函数.
     *
     * 如果没有传入驱动，默认使用内存驱动
     *
     * @param null|DriverInterface $driver 记忆驱动
     */
    public function __construct(?DriverInterface $driver = null, ?PolicyInterface $policy = null)
    {
        $this->driver = $driver ?? new InMemoryDriver();
        if ($policy) {
            $this->setPolicy($policy);
        }
    }

    /**
     * 添加消息到记忆上下文.
     *
     * @param MessageInterface $message 要添加的消息对象
     * @return self 支持链式调用
     */
    public function addMessage(MessageInterface $message): self
    {
        $this->driver->addMessage($message);
        // 清除处理缓存
        $this->invalidateCache();
        return $this;
    }

    public function addSystemMessage(MessageInterface $message): self
    {
        $this->driver->addSystemMessage($message);
        // 清除处理缓存
        $this->invalidateCache();
        return $this;
    }

    /**
     * 获取所有普通消息.
     *
     * @return MessageInterface[] 所有普通消息列表
     */
    public function getMessages(): array
    {
        return $this->driver->getMessages();
    }

    /**
     * 获取所有系统消息.
     *
     * @return MessageInterface[] 所有系统消息列表
     */
    public function getSystemMessages(): array
    {
        return $this->driver->getSystemMessages();
    }

    /**
     * 获取经过策略处理后的所有消息（系统消息+普通消息）.
     *
     * @return MessageInterface[] 处理后的消息列表
     */
    public function getProcessedMessages(): array
    {
        // 使用缓存，避免重复处理
        if ($this->processedCache !== null) {
            return $this->processedCache;
        }

        // 合并系统消息和普通消息
        $allMessages = array_merge($this->getSystemMessages(), $this->getMessages());

        // 如果有策略，应用策略处理
        if ($this->policy !== null) {
            $allMessages = $this->policy->process($allMessages);
        }

        // 缓存处理结果
        $this->processedCache = $allMessages;

        return $allMessages;
    }

    /**
     * 清空所有消息.
     *
     * @return self 支持链式调用
     */
    public function clear(): self
    {
        $this->driver->clear();
        // 清除处理缓存
        $this->invalidateCache();
        return $this;
    }

    /**
     * 设置记忆策略.
     *
     * @param PolicyInterface $policy 要设置的策略对象
     * @return self 支持链式调用
     */
    public function setPolicy(PolicyInterface $policy): self
    {
        $this->policy = $policy;
        // 更新策略后清除缓存
        $this->invalidateCache();
        return $this;
    }

    /**
     * 获取当前记忆策略.
     *
     * @return null|PolicyInterface 当前策略或null
     */
    public function getPolicy(): ?PolicyInterface
    {
        return $this->policy;
    }

    /**
     * 应用当前设置的策略处理消息.
     *
     * 如果没有设置策略，则不做任何处理
     *
     * @return self 支持链式调用
     */
    public function applyPolicy(): self
    {
        // 强制更新处理缓存
        $this->invalidateCache();
        $this->getProcessedMessages();
        return $this;
    }

    /**
     * 清除处理后的消息缓存.
     */
    protected function invalidateCache(): void
    {
        $this->processedCache = null;
    }
}

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

namespace Hyperf\Odin\Contract\Memory;

use Hyperf\Odin\Contract\Message\MessageInterface;

/**
 * 记忆管理接口.
 *
 * 负责管理整体记忆流程，协调驱动和策略的交互
 */
interface MemoryInterface
{
    /**
     * 添加消息到记忆上下文.
     *
     * @param MessageInterface $message 要添加的消息对象
     * @return self 支持链式调用
     */
    public function addMessage(MessageInterface $message): self;

    /**
     * 添加系统消息到记忆上下文.
     *
     * @param MessageInterface $message 要添加的系统消息对象
     * @return self 支持链式调用
     */
    public function addSystemMessage(MessageInterface $message): self;

    /**
     * 获取所有普通消息.
     *
     * @return MessageInterface[] 所有普通消息列表
     */
    public function getMessages(): array;

    /**
     * 获取所有系统消息.
     *
     * @return MessageInterface[] 所有系统消息列表
     */
    public function getSystemMessages(): array;

    /**
     * 获取经过策略处理后的所有消息（系统消息+普通消息）.
     *
     * @return MessageInterface[] 处理后的消息列表
     */
    public function getProcessedMessages(): array;

    /**
     * 清空所有消息.
     *
     * @return self 支持链式调用
     */
    public function clear(): self;

    /**
     * 设置记忆策略.
     *
     * @param PolicyInterface $policy 要设置的策略对象
     * @return self 支持链式调用
     */
    public function setPolicy(PolicyInterface $policy): self;

    /**
     * 获取当前记忆策略.
     *
     * @return null|PolicyInterface 当前策略或null
     */
    public function getPolicy(): ?PolicyInterface;

    /**
     * 应用当前设置的策略处理消息.
     *
     * 如果没有设置策略，则不做任何处理
     *
     * @return self 支持链式调用
     */
    public function applyPolicy(): self;
}

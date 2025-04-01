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

use Hyperf\Odin\Message\AbstractMessage;

/**
 * 记忆驱动接口.
 *
 * 负责消息的实际存储和检索
 */
interface DriverInterface
{
    /**
     * 添加消息到记忆上下文.
     *
     * @param AbstractMessage $message 要添加的消息对象
     */
    public function addMessage(AbstractMessage $message): void;

    /**
     * 添加系统消息到记忆上下文.
     *
     * @param AbstractMessage $message 要添加的系统消息对象
     */
    public function addSystemMessage(AbstractMessage $message): void;

    /**
     * 获取所有普通消息.
     *
     * @return AbstractMessage[]
     */
    public function getMessages(): array;

    /**
     * 获取所有系统消息.
     *
     * @return AbstractMessage[]
     */
    public function getSystemMessages(): array;

    /**
     * 清空所有消息.
     */
    public function clear(): void;
}

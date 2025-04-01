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
 * 记忆策略接口.
 *
 * 负责决定哪些消息应该被保留或丢弃
 */
interface PolicyInterface
{
    /**
     * 处理消息列表，返回经过策略处理后的消息列表.
     *
     * @param MessageInterface[] $messages 原始消息列表
     * @return MessageInterface[] 处理后的消息列表
     */
    public function process(array $messages): array;

    /**
     * 配置策略参数.
     *
     * @param array $options 配置选项
     * @return self 支持链式调用
     */
    public function configure(array $options): self;
}

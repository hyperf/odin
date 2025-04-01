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

use Hyperf\Odin\Message\AbstractMessage;

/**
 * 时间窗口策略.
 *
 * 根据消息时间戳，只保留特定时间窗口内的消息
 */
class TimeWindowPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，只保留指定时间窗口内的消息.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    public function process(array $messages): array
    {
        // TODO: 实现基于时间窗口的消息过滤逻辑
        return $messages;
    }

    /**
     * 获取默认配置选项.
     *
     * @return array 默认配置选项
     */
    protected function getDefaultOptions(): array
    {
        return [
            'window_minutes' => 60, // 默认时间窗口为60分钟
        ];
    }
}

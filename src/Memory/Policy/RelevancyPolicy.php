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
 * 相关性策略.
 *
 * 根据消息与当前上下文的相关性，保留最相关的消息
 */
class RelevancyPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，根据相关性过滤消息.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    public function process(array $messages): array
    {
        // TODO: 实现基于相关性的消息过滤逻辑
        // 需要集成向量数据库或嵌入模型进行相关性计算
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
            'min_relevance_score' => 0.7, // 最小相关性分数
            'max_messages' => 10,         // 最多保留消息数
        ];
    }
}

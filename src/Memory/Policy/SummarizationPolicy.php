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
 * 消息摘要策略.
 *
 * 将历史消息摘要为系统消息，减少上下文长度
 */
class SummarizationPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，将部分历史消息摘要为系统消息.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    public function process(array $messages): array
    {
        // TODO: 实现消息摘要逻辑
        // 需要利用 LLM 对消息进行摘要，生成系统消息
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
            'summarize_threshold' => 15,    // 触发摘要的消息数量阈值
            'keep_recent' => 5,             // 保留最近的消息数量
            'summary_prompt' => '请总结以下对话内容，提取关键信息：', // 摘要提示词
        ];
    }
}

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
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;

/**
 * 数量限制策略.
 *
 * 根据消息数量限制保留最新的消息，同时保留第一条用户消息，并优先删除工具调用相关消息
 */
class LimitCountPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，根据策略优化消息列表.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] A处理后的消息列表
     */
    public function process(array $messages): array
    {
        $maxCount = $this->getOption('max_count', 10);

        // 如果消息数量小于或等于限制，则直接返回
        if (count($messages) <= $maxCount) {
            return $messages;
        }

        // 保存第一个用户消息
        // 这样做的原因：
        // 1. 保持对话上下文完整性：第一条用户消息通常包含了整个对话的初始问题或主要目的
        // 2. 避免语义漂移：在长对话中，如果丢失了初始问题，AI可能会偏离原始主题
        // 3. 提高对话质量：即使在消息数量超出限制时，保留初始指令也能让AI模型始终记住用户的核心需求
        // 4. 优化Token使用：在有限的上下文窗口中，保留初始意图比保留中间过程通常更有价值
        $firstUserMessage = null;
        foreach ($messages as $message) {
            if ($message instanceof UserMessage) {
                $firstUserMessage = $message;
                break;
            }
        }

        // 查找并删除所有带有工具调用的 Assistant 消息及其相关的 Tool 消息
        $messagesToDelete = [];
        $messageCount = count($messages);

        foreach ($messages as $index => $message) {
            if ($message instanceof AssistantMessage) {
                $toolCalls = $message->getToolCalls();
                if (! empty($toolCalls)) {
                    // 将当前 Assistant 消息标记为删除
                    $messagesToDelete[] = $message;

                    // 查找并标记相关的 Tool 消息
                    for ($i = $index + 1; $i < $messageCount; ++$i) {
                        $nextMessage = $messages[$i];
                        if ($nextMessage instanceof ToolMessage) {
                            foreach ($toolCalls as $toolCall) {
                                if ($nextMessage->getToolCallId() === $toolCall->getId()) {
                                    $messagesToDelete[] = $nextMessage;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // 从消息列表中删除标记的消息
        foreach ($messagesToDelete as $messageToDelete) {
            $key = array_search($messageToDelete, $messages, true);
            if ($key !== false) {
                unset($messages[$key]);
            }
        }

        // 重建索引
        $messages = array_values($messages);

        // 如果消息数量仍然超过限制，保留最新的消息
        if (count($messages) > $maxCount) {
            $messages = array_slice($messages, -$maxCount + 1); // 留出一个位置给第一个用户消息
        }

        // 如果有第一个用户消息且不在当前消息列表中，则添加到开头
        if ($firstUserMessage !== null) {
            // 检查第一个用户消息是否已经在列表中
            $firstUserMessageExists = false;
            foreach ($messages as $message) {
                if ($message === $firstUserMessage) {
                    $firstUserMessageExists = true;
                    break;
                }
            }

            // 如果不在列表中，添加到开头
            if (! $firstUserMessageExists) {
                array_unshift($messages, $firstUserMessage);
            }
        }

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
            'max_count' => 10, // 默认保留最新的 10 条消息
        ];
    }
}

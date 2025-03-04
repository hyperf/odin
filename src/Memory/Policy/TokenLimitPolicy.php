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
 * Token 数量限制策略.
 *
 * 根据估算的 token 数量限制消息
 */
class TokenLimitPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，保留 token 总数在限制内的最新消息.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    public function process(array $messages): array
    {
        $maxTokens = $this->getOption('max_tokens', 4000);
        $tokenRatio = $this->getOption('token_ratio', 3.5); // 约 3.5 个字符一个 token

        // 如果没有消息，直接返回
        if (empty($messages)) {
            return [];
        }

        // 计算每条消息的 token 数量，从最新的消息开始保留
        $result = [];
        $totalTokens = 0;

        // 从最新的消息开始处理
        $reversedMessages = array_reverse($messages);

        foreach ($reversedMessages as $message) {
            $content = $message->getContent();
            $tokenCount = (int) ceil(mb_strlen($content) / $tokenRatio);

            // 如果添加这条消息会超出限制，则停止添加
            if ($totalTokens + $tokenCount > $maxTokens && ! empty($result)) {
                break;
            }

            // 添加消息并累加 token 数量
            $totalTokens += $tokenCount;
            array_unshift($result, $message); // 恢复原始顺序
        }

        return $result;
    }

    /**
     * 获取默认配置选项.
     *
     * @return array 默认配置选项
     */
    protected function getDefaultOptions(): array
    {
        return [
            'max_tokens' => 4000, // 默认最大 token 数量，适用于大部分大语言模型
            'token_ratio' => 3.5, // 字符与 token 的大致换算比例
        ];
    }
}

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

namespace Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy;

/**
 * 用于记录缓存点的消息管理器.
 * 类似 AWS Bedrock 的 DynamicMessageCacheManager，但适配 Gemini 的单缓存点机制.
 */
class GeminiMessageCacheManager
{
    /**
     * 已经是排序好的数据.
     * 索引说明：
     * - 0: tools
     * - 1: system message
     * - 2+: user/assistant/tool messages.
     *
     * @var array<int, CachePointMessage>
     */
    private array $cachePointMessages;

    public function __construct(array $cachePointMessages)
    {
        ksort($cachePointMessages);
        $this->cachePointMessages = $cachePointMessages;
    }

    /**
     * 获取缓存 key（基于 model + tools + system 的 hash）.
     * 注意：不包含动态内容（user messages），只包含稳定的上下文.
     */
    public function getCacheKey(string $model): string
    {
        return 'gemini_cache:' . md5($model . $this->getToolsHash() . $this->getSystemMessageHash());
    }

    /**
     * 获取前缀 hash（system + tools）.
     * 注意：不包含动态内容（user messages），只包含稳定的上下文.
     */
    public function getPrefixHash(string $model): string
    {
        return md5($model . $this->getToolsHash() . $this->getSystemMessageHash());
    }

    public function getToolsHash(): string
    {
        if (! isset($this->cachePointMessages[0])) {
            return '';
        }
        return $this->cachePointMessages[0]->getHash() ?? '';
    }

    public function getSystemMessageHash(): string
    {
        if (! isset($this->cachePointMessages[1])) {
            return '';
        }
        return $this->cachePointMessages[1]->getHash() ?? '';
    }

    /**
     * 获取第一个 user message 的 hash.
     */
    public function getFirstUserMessageHash(): string
    {
        // 查找第一个 user message（索引从 2 开始）
        for ($i = 2; $i < count($this->cachePointMessages); ++$i) {
            if (isset($this->cachePointMessages[$i])) {
                return $this->cachePointMessages[$i]->getHash() ?? '';
            }
        }
        return '';
    }

    public function getToolTokens(): int
    {
        if (! isset($this->cachePointMessages[0])) {
            return 0;
        }
        return $this->cachePointMessages[0]->getTokens() ?? 0;
    }

    public function getSystemTokens(): int
    {
        if (! isset($this->cachePointMessages[1])) {
            return 0;
        }
        return $this->cachePointMessages[1]->getTokens() ?? 0;
    }

    /**
     * 获取第一个 user message 的 tokens.
     */
    public function getFirstUserMessageTokens(): int
    {
        // 查找第一个 user message（索引从 2 开始）
        for ($i = 2; $i < count($this->cachePointMessages); ++$i) {
            if (isset($this->cachePointMessages[$i])) {
                return $this->cachePointMessages[$i]->getTokens() ?? 0;
            }
        }
        return 0;
    }

    /**
     * 获取缓存前缀的总 tokens（system + tools + 第一个 user message）.
     */
    public function getPrefixTokens(): int
    {
        return $this->getToolTokens() + $this->getSystemTokens() + $this->getFirstUserMessageTokens();
    }

    /**
     * 获取基础前缀 tokens（只包含 system + tools，不包含第一个 user message）.
     * 用于第一次创建缓存时使用.
     */
    public function getBasePrefixTokens(): int
    {
        return $this->getToolTokens() + $this->getSystemTokens();
    }

    /**
     * 获取基础前缀 hash（只包含 system + tools，不包含第一个 user message）.
     * 用于第一次创建缓存时使用.
     */
    public function getBasePrefixHash(string $model): string
    {
        return md5($model . $this->getToolsHash() . $this->getSystemMessageHash());
    }

    public function getCachePointMessages(): array
    {
        return $this->cachePointMessages;
    }

    /**
     * 获取最后一条消息的索引.
     */
    public function getLastMessageIndex(): int
    {
        return count($this->cachePointMessages) - 1;
    }

    /**
     * 判断对话是否连续（通过比较前缀 hash）.
     */
    public function isContinuousConversation(GeminiMessageCacheManager $lastManager, string $model): bool
    {
        return $this->getPrefixHash($model) === $lastManager->getPrefixHash($model);
    }

    /**
     * 计算特定范围消息的总Token数.
     * 用于计算增量 tokens（从缓存点之后到最新消息）.
     */
    public function calculateTotalTokens(int $startIndex, int $endIndex): int
    {
        if ($endIndex < $startIndex) {
            return 0;
        }
        $totalTokens = 0;

        for ($i = $startIndex; $i <= $endIndex; ++$i) {
            if (isset($this->cachePointMessages[$i])) {
                $totalTokens += $this->cachePointMessages[$i]?->getTokens() ?? 0;
            }
        }

        return $totalTokens;
    }

    /**
     * 获取第一个 user message 的索引.
     */
    public function getFirstUserMessageIndex(): ?int
    {
        // 查找第一个 user message（索引从 2 开始）
        for ($i = 2; $i < count($this->cachePointMessages); ++$i) {
            if (isset($this->cachePointMessages[$i])) {
                return $i;
            }
        }
        return null;
    }
}

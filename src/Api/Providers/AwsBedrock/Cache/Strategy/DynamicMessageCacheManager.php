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

namespace Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy;

/**
 * 用于记录缓存点.
 */
class DynamicMessageCacheManager
{
    /**
     * @var array<int>
     */
    private array $cachePointIndex = [];

    /**
     * 已经是排序好的数据.
     * @var array<int, CachePointMessage>
     */
    private array $cachePointMessages;

    public function __construct(array $cachePointMessages)
    {
        ksort($cachePointMessages);
        $this->cachePointMessages = $cachePointMessages;
    }

    public function getCacheKey(string $prefix): string
    {
        return 'aws_dynamic_cache:' . md5($prefix . $this->getToolsHash() . $this->getSystemMessageHash());
    }

    public function getToolsHash(): string
    {
        return $this->cachePointMessages[0]?->getHash() ?? '';
    }

    public function getSystemMessageHash(): string
    {
        return $this->cachePointMessages[1]?->getHash() ?? '';
    }

    public function getToolTokens(): int
    {
        return $this->cachePointMessages[0]?->getTokens() ?? 0;
    }

    public function getSystemTokens(): int
    {
        return $this->cachePointMessages[1]?->getTokens() ?? 0;
    }

    public function addCachePointIndex(int $index): void
    {
        if (! in_array($index, $this->cachePointIndex, true)) {
            $this->cachePointIndex[] = $index;
        }
    }

    public function getLastCachePointIndex(): ?int
    {
        return $this->cachePointIndex ? max($this->cachePointIndex) : null;
    }

    public function getCachePointIndex(): array
    {
        return $this->cachePointIndex;
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

    public function loadHistoryCachePoint(DynamicMessageCacheManager $lastDynamicMessageCacheManager): bool
    {
        foreach ($lastDynamicMessageCacheManager->getCachePointMessages() as $index => $cachePointMessage) {
            if ($cachePointMessage->getHash() !== $this->cachePointMessages[$index]->getHash()) {
                return false;
            }
        }
        $this->cachePointIndex = $lastDynamicMessageCacheManager->getCachePointIndex();
        return true;
    }

    /**
     * 计算特定范围消息的总Token数.
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

    public function resetPointIndex(int $maxCachePoints): void
    {
        if (count($this->cachePointIndex) <= $maxCachePoints) {
            return;
        }
        $cachePointIndex = [];
        // 保持固定机位
        if (in_array(0, $this->cachePointIndex, true)) {
            $cachePointIndex[] = [0];
            --$maxCachePoints;
        } elseif (in_array(1, $this->cachePointIndex, true)) {
            $cachePointIndex[] = [1];
            --$maxCachePoints;
        }
        $this->cachePointIndex = array_slice($this->cachePointIndex, -$maxCachePoints);
        array_unshift($this->cachePointIndex, ...$cachePointIndex);
    }
}

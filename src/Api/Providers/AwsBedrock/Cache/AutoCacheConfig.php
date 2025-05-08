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

namespace Hyperf\Odin\Api\Providers\AwsBedrock\Cache;

class AutoCacheConfig
{
    /**
     * 最大缓存点数量.
     */
    private int $maxCachePoints;

    /**
     * 缓存点最小生效 tokens 阈值
     */
    private int $minCacheTokens;

    /**
     * 刷新缓存点的最小 tokens 阈值.
     * 达到这个缓存点将重新评估缓存点.
     */
    private int $refreshPointMinTokens;

    /**
     * 缓存点命中最小次数.
     * 达到最小命中次数后，才会进行缓存点的评估.
     */
    private int $minHitCount;

    public function __construct(
        int $maxCachePoints = 4,
        int $minCacheTokens = 2048,
        int $refreshPointMinTokens = 5000,
        int $minHitCount = 3
    ) {
        $this->maxCachePoints = $maxCachePoints;
        $this->minCacheTokens = $minCacheTokens;
        $this->refreshPointMinTokens = $refreshPointMinTokens;
        $this->minHitCount = $minHitCount;
    }

    public function getMaxCachePoints(): int
    {
        return $this->maxCachePoints;
    }

    public function getMinCacheTokens(): int
    {
        return $this->minCacheTokens;
    }

    public function getRefreshPointMinTokens(): int
    {
        return $this->refreshPointMinTokens;
    }

    public function getMinHitCount(): int
    {
        return $this->minHitCount;
    }
}

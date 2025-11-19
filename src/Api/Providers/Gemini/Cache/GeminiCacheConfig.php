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

namespace Hyperf\Odin\Api\Providers\Gemini\Cache;

class GeminiCacheConfig
{
    /**
     * 缓存点最小生效 tokens 阈值.
     * 根据模型不同：
     * - Gemini 2.5 Flash: 1024
     * - Gemini 2.5 Pro: 4096
     * - Gemini 3 Pro Preview: 2048.
     */
    private int $minCacheTokens;

    /**
     * 刷新缓存点的最小 tokens 阈值.
     * 达到这个阈值将重新评估缓存点.
     */
    private int $refreshPointMinTokens;

    /**
     * 缓存过期时间（秒）.
     */
    private int $ttl;

    /**
     * 是否启用自动缓存.
     */
    private bool $enableAutoCache;

    public function __construct(
        int $minCacheTokens = 1024,
        int $refreshPointMinTokens = 5000,
        int $ttl = 600,
        bool $enableAutoCache = false
    ) {
        $this->minCacheTokens = $minCacheTokens;
        $this->refreshPointMinTokens = $refreshPointMinTokens;
        $this->ttl = $ttl;
        $this->enableAutoCache = $enableAutoCache;
    }

    public function getMinCacheTokens(): int
    {
        return $this->minCacheTokens;
    }

    public function getRefreshPointMinTokens(): int
    {
        return $this->refreshPointMinTokens;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function isEnableAutoCache(): bool
    {
        return $this->enableAutoCache;
    }

    /**
     * 根据模型名称获取最小缓存 tokens 阈值.
     */
    public static function getMinCacheTokensByModel(string $model): int
    {
        return match (true) {
            str_contains($model, '2.5-flash') || str_contains($model, 'flash') => 1024,
            str_contains($model, '2.5-pro') || str_contains($model, 'pro') => 4096,
            str_contains($model, '3-pro-preview') || str_contains($model, '3-pro') => 2048,
            default => 4096, // 默认使用最大值（2.5 Pro 的阈值）
        };
    }
}

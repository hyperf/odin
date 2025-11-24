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

/**
 * Gemini cache configuration.
 * Unified cache strategy configuration for conversation caching.
 */
class GeminiCacheConfig
{
    /**
     * Enable cache (master switch).
     */
    private bool $enableCache;

    /**
     * Minimum tokens threshold for creating cache.
     * For initial cache (system+tools), this is the minimum.
     * Default: 32768 tokens.
     */
    private int $minCacheTokens;

    /**
     * Cache refresh threshold (incremental tokens from last cache).
     * When conversation grows by this many tokens, cache will be updated.
     * Default: 8000 tokens.
     */
    private int $refreshThreshold;

    /**
     * Cache TTL in seconds.
     * Range: 60s - 86400s (24 hours).
     * Default: 3600 seconds (1 hour).
     */
    private int $cacheTtl;

    /**
     * Estimation ratio for token count adjustment.
     * This ratio is applied to all token estimations to get more accurate values.
     * Value range: 0.0 - 1.0 (e.g., 0.33 means actual tokens are typically 33% of estimated).
     *
     * Based on real-world data: Gemini actual tokens are typically ~32% of estimated tokens.
     * We use 0.33 as a slightly conservative value.
     */
    private float $estimationRatio;

    public function __construct(
        bool $enableCache = false,
        int $minCacheTokens = 4096,
        int $refreshThreshold = 8000,
        int $cacheTtl = 600,
        float $estimationRatio = 0.33
    ) {
        $this->enableCache = $enableCache;
        $this->minCacheTokens = $minCacheTokens;
        $this->refreshThreshold = $refreshThreshold;
        $this->cacheTtl = max(60, min(86400, $cacheTtl)); // Clamp to 60s-86400s
        $this->estimationRatio = max(0.0, min(1.0, $estimationRatio)); // Clamp to 0.0-1.0
    }

    public function isEnableCache(): bool
    {
        return $this->enableCache;
    }

    public function getMinCacheTokens(): int
    {
        return $this->minCacheTokens;
    }

    public function getRefreshThreshold(): int
    {
        return $this->refreshThreshold;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function getEstimationRatio(): float
    {
        return $this->estimationRatio;
    }

    /**
     * Get minimum cache tokens by model name.
     * Based on official documentation:
     * - Gemini 2.5 Flash / 2.0 Flash / 3.0 Flash: 2048 tokens
     * - Gemini 2.5 Pro / 2.0 Pro / 3.0 Pro: 4096 tokens.
     */
    public static function getMinCacheTokensByModel(string $model): int
    {
        $modelLower = strtolower($model);

        return match (true) {
            // Gemini 2.5 Flash
            str_contains($modelLower, 'gemini-2.5-flash')
            || str_contains($modelLower, 'gemini-2-flash')
            || str_contains($modelLower, 'gemini-3-flash')
            || str_contains($modelLower, 'gemini-3.0-flash') => 2048,

            // Gemini 2.5 Pro / 2.0 Pro / 3.0 Pro
            str_contains($modelLower, 'gemini-2.5-pro')
            || str_contains($modelLower, 'gemini-2-pro')
            || str_contains($modelLower, 'gemini-3-pro')
            || str_contains($modelLower, 'gemini-3.0-pro') => 4096,

            // Default: use the highest threshold to be safe
            default => 4096,
        };
    }
}

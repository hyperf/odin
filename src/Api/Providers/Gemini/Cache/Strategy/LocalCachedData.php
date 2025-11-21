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
 * Local cached data object.
 * Represents cache data stored in local cache (Redis/Memory).
 */
class LocalCachedData
{
    /**
     * @param array<string> $cachedMessageHashes
     */
    public function __construct(
        private string $cacheName,
        private string $model,
        private ?int $actualCachedTokens,
        private int $estimatedCachedTokens,
        private array $cachedMessageHashes,
        private int $createdAt
    ) {}

    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getActualCachedTokens(): ?int
    {
        return $this->actualCachedTokens;
    }

    public function getEstimatedCachedTokens(): int
    {
        return $this->estimatedCachedTokens;
    }

    /**
     * @return array<string>
     */
    public function getCachedMessageHashes(): array
    {
        return $this->cachedMessageHashes;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'cache_name' => $this->cacheName,
            'model' => $this->model,
            'actual_cached_tokens' => $this->actualCachedTokens,
            'estimated_cached_tokens' => $this->estimatedCachedTokens,
            'cached_message_hashes' => $this->cachedMessageHashes,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Create from array retrieved from cache.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cacheName: $data['cache_name'] ?? '',
            model: $data['model'] ?? '',
            actualCachedTokens: $data['actual_cached_tokens'] ?? null,
            estimatedCachedTokens: $data['estimated_cached_tokens'] ?? 0,
            cachedMessageHashes: $data['cached_message_hashes'] ?? [],
            createdAt: $data['created_at'] ?? time()
        );
    }

    /**
     * Get the last cached tokens (prefer estimated, fallback to actual).
     * Used for comparison in shouldUpdateCache.
     */
    public function getLastCachedTokens(): int
    {
        return $this->estimatedCachedTokens ?? $this->actualCachedTokens ?? 0;
    }
}

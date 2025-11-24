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
 * Cache information object.
 * Encapsulates cache details returned from cache strategy.
 */
class CacheInfo
{
    /**
     * Cache name (e.g., cachedContents/xxx).
     */
    private string $cacheName;

    /**
     * Whether this cache was newly created in this request.
     */
    private bool $isNewlyCreated;

    /**
     * Tokens written to cache (0 if using existing cache).
     */
    private int $cacheWriteTokens;

    /**
     * Hashes of cached messages.
     * Used to filter out cached messages when applying cache.
     *
     * @var array<string>
     */
    private array $cachedMessageHashes;

    /**
     * @param array<string> $cachedMessageHashes
     */
    public function __construct(
        string $cacheName,
        bool $isNewlyCreated,
        int $cacheWriteTokens,
        array $cachedMessageHashes = []
    ) {
        $this->cacheName = $cacheName;
        $this->isNewlyCreated = $isNewlyCreated;
        $this->cacheWriteTokens = $cacheWriteTokens;
        $this->cachedMessageHashes = $cachedMessageHashes;
    }

    public function getCacheName(): string
    {
        return $this->cacheName;
    }

    public function isNewlyCreated(): bool
    {
        return $this->isNewlyCreated;
    }

    public function getCacheWriteTokens(): int
    {
        return $this->cacheWriteTokens;
    }

    /**
     * @return array<string>
     */
    public function getCachedMessageHashes(): array
    {
        return $this->cachedMessageHashes;
    }

    /**
     * Convert to array (for logging or serialization).
     */
    public function toArray(): array
    {
        return [
            'cache_name' => $this->cacheName,
            'is_newly_created' => $this->isNewlyCreated,
            'cache_write_tokens' => $this->cacheWriteTokens,
            'cached_message_hashes' => $this->cachedMessageHashes,
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['cache_name'] ?? '',
            $data['is_newly_created'] ?? false,
            $data['cache_write_tokens'] ?? 0,
            $data['cached_message_hashes'] ?? []
        );
    }
}

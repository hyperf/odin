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
 * Message cache manager for Gemini caching.
 * Manages cache point messages (tools, system, user messages) and their hashes.
 * Used by both GlobalCacheStrategy and UserCacheStrategy for:
 * - Calculating prefix hash (tools + system) for cache key
 * - Checking conversation continuity
 * - Token calculations.
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

    public function getCacheKey(string $model): string
    {
        return 'gemini_cache:' . md5($model . $this->getToolsHash() . $this->getSystemMessageHash() . $this->getFirstUserMessageHash());
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
        if (! isset($this->cachePointMessages[2])) {
            return '';
        }
        return $this->cachePointMessages[2]->getHash() ?? '';
    }

    public function getCachePointMessages(): array
    {
        return $this->cachePointMessages;
    }
}

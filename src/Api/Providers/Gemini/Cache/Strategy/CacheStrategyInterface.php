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

use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;

interface CacheStrategyInterface
{
    /**
     * Apply cache strategy to the request (called before request).
     * Check if cache is available and return cache info.
     *
     * @param GeminiCacheConfig $config Cache configuration
     * @param ChatCompletionRequest $request Request object
     * @return null|array Cache info, containing cache_name, has_system, has_tools, cached_message_count, or null if no cache
     */
    public function apply(GeminiCacheConfig $config, ChatCompletionRequest $request): ?array;

    /**
     * Create or update cache after request (called after request).
     * This method is called after a successful request to create or update cache if needed.
     *
     * @param GeminiCacheConfig $config Cache configuration
     * @param ChatCompletionRequest $request Request object
     */
    public function createOrUpdateCache(GeminiCacheConfig $config, ChatCompletionRequest $request): void;
}

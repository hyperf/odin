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

use Hyperf\Odin\Api\Providers\Gemini\Cache\CacheInfo;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;

interface CacheStrategyInterface
{
    /**
     * Apply cache strategy to the request (called before request).
     * Check if cache is available, create new cache if needed, and return cache info.
     *
     * @param GeminiCacheConfig $config Cache configuration
     * @param ChatCompletionRequest $request Request object
     * @return null|CacheInfo Cache information object or null if no cache
     */
    public function apply(GeminiCacheConfig $config, ChatCompletionRequest $request): ?CacheInfo;
}

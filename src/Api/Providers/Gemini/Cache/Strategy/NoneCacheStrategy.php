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

/**
 * None cache strategy - no caching applied.
 */
class NoneCacheStrategy implements CacheStrategyInterface
{
    public function apply(GeminiCacheConfig $config, ChatCompletionRequest $request): ?array
    {
        return null;
    }

    public function createOrUpdateCache(GeminiCacheConfig $config, ChatCompletionRequest $request): void
    {
        // None cache strategy does nothing
    }
}

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

use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\CacheStrategyInterface;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\ConversationCacheStrategy;
use Hyperf\Odin\Api\Providers\Gemini\GeminiConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;

/**
 * Gemini cache manager.
 * Manages conversation caching using a unified progressive cache strategy.
 */
class GeminiCacheManager
{
    private GeminiCacheConfig $config;

    private ?ApiOptions $apiOptions;

    private ?GeminiConfig $geminiConfig;

    private ?LoggerInterface $logger;

    public function __construct(
        GeminiCacheConfig $config,
        ?ApiOptions $apiOptions = null,
        ?GeminiConfig $geminiConfig = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->config = $config;
        $this->apiOptions = $apiOptions;
        $this->geminiConfig = $geminiConfig;
        $this->logger = $logger;
    }

    /**
     * Check or create cache (called before request).
     *
     * @param ChatCompletionRequest $request Request object
     * @return null|CacheInfo Cache information object or null if no cache conditions are met
     */
    public function checkCache(ChatCompletionRequest $request): ?CacheInfo
    {
        // Use conversation cache strategy
        $strategy = $this->createStrategy();
        $cacheInfo = $strategy->apply($this->config, $request);

        if ($cacheInfo) {
            $this->logger?->info('Cache applied', [
                'cache_name' => $cacheInfo->getCacheName(),
                'is_newly_created' => $cacheInfo->isNewlyCreated(),
                'cache_write_tokens' => $cacheInfo->getCacheWriteTokens(),
            ]);
        }

        return $cacheInfo;
    }

    /**
     * Create conversation cache strategy instance with proper dependencies.
     */
    private function createStrategy(): CacheStrategyInterface
    {
        // 目前就先这样吧，就一个
        $cacheClient = new GeminiCacheClient($this->geminiConfig, $this->apiOptions, $this->logger);
        return new ConversationCacheStrategy($cacheClient, $this->logger);
    }
}

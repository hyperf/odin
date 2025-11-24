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

use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Api\Providers\Gemini\Cache\CacheInfo;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheClient;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\RequestHandler;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Utils\ToolUtil;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Conversation cache strategy - unified caching for conversations.
 * Implements progressive caching:
 * - Initial: cache system+tools only
 * - Growth: cache system+tools+historical_messages (excluding last message)
 * - Only works for continuous conversations.
 */
class ConversationCacheStrategy implements CacheStrategyInterface
{
    private CacheInterface $cache;

    private GeminiCacheClient $cacheClient;

    private ?LoggerInterface $logger;

    public function __construct(
        GeminiCacheClient $cacheClient,
        ?LoggerInterface $logger = null,
    ) {
        $this->cache = ApplicationContext::getContainer()->get(CacheInterface::class);
        $this->cacheClient = $cacheClient;
        $this->logger = $logger;
    }

    /**
     * Apply cache strategy to request.
     *
     * Logic:
     * 1. Check if cache is enabled
     * 2. Get cache key
     * 3. Try to get from local cache
     * 4. If no cache, create initial cache (system+tools)
     * 5. If has cache, check if conversation is continuous
     * 6. If continuous, check if should update cache
     * 7. Return cache info or null
     */
    public function apply(GeminiCacheConfig $config, ChatCompletionRequest $request): ?CacheInfo
    {
        if (! $config->isEnableCache()) {
            return null;
        }
        $messages = $request->getMessages();
        if (empty($messages)) {
            return null;
        }
        $messageCacheManager = $this->createMessageCacheManager($request);

        // 至少需要 4 个消息点（tools + system + user），才考虑缓存，此时会缓存前 3 个消息，最后一个消息在本次用于请求
        if (count($messageCacheManager->getCachePointMessages()) < 4) {
            $this->logger?->debug('Not enough message points for caching');
            return null;
        }

        // Get cache key
        $cacheKey = $messageCacheManager->getCacheKey($request->getModel());

        // Try to get from local cache
        $cachedData = $this->getLocalCachedData($cacheKey);

        // No existing cache, create initial cache
        if ($cachedData === null) {
            return $this->createInitialCache($config, $request, $cacheKey);
        }

        // Check if you should update cache
        if ($this->shouldUpdateCache($config, $cachedData, $request)) {
            return $this->updateCache($config, $cachedData, $request, $cacheKey);
        }

        // Use existing cache
        $this->logger?->info('Using existing cache', [
            'cache_name' => $cachedData->getCacheName(),
        ]);

        return new CacheInfo(
            cacheName: $cachedData->getCacheName(),
            isNewlyCreated: false,
            cacheWriteTokens: 0,
            cachedMessageHashes: $cachedData->getCachedMessageHashes()
        );
    }

    private function createMessageCacheManager(ChatCompletionRequest $request): GeminiMessageCacheManager
    {
        $index = 2;
        // tools 也当做是一个消息
        $toolsArray = ToolUtil::filter($request->getTools());
        $cachePointMessages[0] = new CachePointMessage($toolsArray, $request->getToolsTokenEstimate() ?? 0);
        foreach ($request->getMessages() as $message) {
            if ($message instanceof SystemMessage) {
                $cachePointMessages[1] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
            } else {
                $cachePointMessages[$index] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
                ++$index;
            }
        }

        return new GeminiMessageCacheManager($cachePointMessages);
    }

    /**
     * Create initial cache (system+tools or system+tools+first_messages).
     * Initial cache is created when:
     * - No existing cache
     * - Estimated cache content meets minimum token threshold.
     */
    private function createInitialCache(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        string $cacheKey
    ): ?CacheInfo {
        $estimatedCachedTokens = $this->calculateEstimatedCachedTokens($config, $request);

        // Check minimum threshold
        $minTokens = max(
            $config->getMinCacheTokens(),
            GeminiCacheConfig::getMinCacheTokensByModel($request->getModel())
        );

        if ($estimatedCachedTokens < $minTokens) {
            $this->logger?->debug('Cache not created: below minimum tokens', [
                'estimated_cached_tokens' => $estimatedCachedTokens,
                'min_tokens' => $minTokens,
            ]);
            return null;
        }

        try {
            $this->logger?->info('Creating initial cache', [
                'model' => $request->getModel(),
                'estimated_cached_tokens' => $estimatedCachedTokens,
            ]);

            return $this->performCacheCreation($config, $request, $cacheKey, $estimatedCachedTokens, 'Initial');
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to create initial cache', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if cache should be updated.
     * Update when: incremental tokens reach refresh threshold.
     */
    private function shouldUpdateCache(
        GeminiCacheConfig $config,
        LocalCachedData $cachedData,
        ChatCompletionRequest $request
    ): bool {
        $currentEstimatedCachedTokens = $this->calculateEstimatedCachedTokens($config, $request);

        // Get last cached tokens
        $lastActualTokens = $cachedData->getActualCachedTokens();
        $lastEstimatedTokens = $cachedData->getEstimatedCachedTokens();

        if ($lastEstimatedTokens === 0 && $lastActualTokens === null) {
            $this->logger?->info('Cache should update: no last cached tokens record');
            return true;
        }

        // Use estimated vs estimated for comparison (most fair)
        $lastTokens = $lastEstimatedTokens ?: ($lastActualTokens ?? 0);
        $incrementalTokens = $currentEstimatedCachedTokens - $lastTokens;

        if ($incrementalTokens <= 0) {
            $this->logger?->debug('Cache should NOT update: no token growth', [
                'current_tokens' => $currentEstimatedCachedTokens,
                'last_tokens' => $lastTokens,
            ]);
            return false;
        }

        $threshold = $config->getRefreshThreshold();
        $shouldUpdate = $incrementalTokens >= $threshold;

        if ($shouldUpdate) {
            $this->logger?->info('Cache should update: threshold reached', [
                'cache_name' => $cachedData->getCacheName(),
                'current_estimated_tokens' => $currentEstimatedCachedTokens,
                'last_tokens' => $lastTokens,
                'incremental_tokens' => $incrementalTokens,
                'threshold' => $threshold,
            ]);
        } else {
            $this->logger?->debug('Cache should NOT update: below threshold', [
                'current_tokens' => $currentEstimatedCachedTokens,
                'last_tokens' => $lastTokens,
                'incremental_tokens' => $incrementalTokens,
                'threshold' => $threshold,
            ]);
        }

        return $shouldUpdate;
    }

    /**
     * Update cache (create new, delete old).
     */
    private function updateCache(
        GeminiCacheConfig $config,
        LocalCachedData $oldCachedData,
        ChatCompletionRequest $request,
        string $cacheKey
    ): CacheInfo {
        try {
            $this->logger?->info('Updating cache', [
                'model' => $request->getModel(),
                'old_cache_name' => $oldCachedData->getCacheName(),
            ]);

            $estimatedCachedTokens = $this->calculateEstimatedCachedTokens($config, $request);
            $cacheInfo = $this->performCacheCreation($config, $request, $cacheKey, $estimatedCachedTokens, 'Cache updated');

            // Delete old cache (async, don't block)
            $oldCacheName = $oldCachedData->getCacheName();
            if ($oldCacheName && $oldCacheName !== $cacheInfo->getCacheName()) {
                $this->deleteOldCache($oldCacheName);
            }

            return $cacheInfo;
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to update cache, using old cache', [
                'error' => $e->getMessage(),
            ]);

            // Update failed, use old cache with 0 write tokens
            return new CacheInfo(
                cacheName: $oldCachedData->getCacheName(),
                isNewlyCreated: false,
                cacheWriteTokens: 0,
                cachedMessageHashes: $oldCachedData->getCachedMessageHashes()
            );
        }
    }

    /**
     * Build cache config for API.
     * Cache content: systemInstruction + tools + historical messages (exclude last).
     */
    private function buildCacheConfig(GeminiCacheConfig $config, ChatCompletionRequest $request): array
    {
        $cacheConfig = [];

        // 1. Add systemInstruction
        $systemMessage = $this->getSystemMessage($request);
        if ($systemMessage) {
            $systemText = $systemMessage->getContent();
            if (! empty($systemText)) {
                $cacheConfig['systemInstruction'] = [
                    'parts' => [
                        ['text' => $systemText],
                    ],
                ];
            }
        }

        // 2. Add tools
        $tools = $request->getTools();
        if (! empty($tools)) {
            $convertedTools = RequestHandler::convertTools($tools);
            if (! empty($convertedTools)) {
                $cacheConfig['tools'] = $convertedTools;
            }
        }

        // 3. Add historical messages (exclude system and last message)
        $messages = $request->getMessages();
        $historicalMessages = array_slice($messages, 0, -1); // Exclude last message

        if (! empty($historicalMessages)) {
            $result = RequestHandler::convertMessages($historicalMessages);
            if (! empty($result['contents'])) {
                $cacheConfig['contents'] = $result['contents'];
            }
        }

        // 4. Set TTL
        $ttl = $config->getCacheTtl();
        $cacheConfig['ttl'] = $ttl . 's';

        return $cacheConfig;
    }

    /**
     * @param array<MessageInterface> $messages
     *                                          Calculate cached message hashes.
     *                                          These are messages that are included in the cache (exclude system and last message).
     */
    private function calculateCachedMessageHashes(array $messages): array
    {
        $hashes = [];

        // Exclude last message (current user message, not cached)
        $messagesToCache = array_slice($messages, 0, -1);

        foreach ($messagesToCache as $message) {
            $hash = $message->getHash();
            if ($hash) {
                $hashes[] = $hash;
            }
        }

        return $hashes;
    }

    /**
     * Get system message from request.
     */
    private function getSystemMessage(ChatCompletionRequest $request): ?SystemMessage
    {
        foreach ($request->getMessages() as $message) {
            if ($message instanceof SystemMessage) {
                return $message;
            }
        }
        return null;
    }

    /**
     * Get local cached data from cache storage.
     * Returns LocalCachedData object if found, null otherwise.
     */
    private function getLocalCachedData(string $cacheKey): ?LocalCachedData
    {
        $cachedDataArray = $this->cache->get($cacheKey);

        if (! is_array($cachedDataArray)) {
            return null;
        }

        return LocalCachedData::fromArray($cachedDataArray);
    }

    /**
     * Calculate estimated cached tokens.
     * Formula: (totalTokens - lastMessageTokens) * estimationRatio.
     */
    private function calculateEstimatedCachedTokens(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request
    ): int {
        $messages = $request->getMessages();
        $totalEstimate = $request->getTotalTokenEstimate() ?? 0;
        $lastMessage = end($messages);
        $lastMessageTokens = $lastMessage->getTokenEstimate() ?? 0;
        $rawEstimate = $totalEstimate - $lastMessageTokens;

        return (int) round($rawEstimate * $config->getEstimationRatio());
    }

    /**
     * Perform cache creation (shared logic for initial and update).
     * Returns CacheInfo with cache details.
     */
    private function performCacheCreation(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        string $cacheKey,
        int $estimatedCachedTokens,
        string $logPrefix
    ): CacheInfo {
        $cacheConfig = $this->buildCacheConfig($config, $request);
        $cacheResponse = $this->cacheClient->createCache($request->getModel(), $cacheConfig);
        $cacheName = $cacheResponse['name'] ?? '';

        // Get actual tokens from API response
        $actualCacheTokens = $cacheResponse['usageMetadata']['totalTokenCount'] ?? null;
        $finalTokens = $actualCacheTokens ?? $estimatedCachedTokens;

        // Calculate cached message hashes
        $messages = $request->getMessages();
        $cachedMessageHashes = $this->calculateCachedMessageHashes($messages);

        // Create LocalCachedData object
        $localCachedData = new LocalCachedData(
            cacheName: $cacheName,
            model: $request->getModel(),
            actualCachedTokens: $actualCacheTokens,
            estimatedCachedTokens: $estimatedCachedTokens,
            cachedMessageHashes: $cachedMessageHashes,
            createdAt: time()
        );

        // Save to local cache
        $this->saveCacheToLocalStorage($cacheKey, $localCachedData, $config->getCacheTtl());

        // Log success
        $this->logCacheOperationSuccess(
            $logPrefix,
            $cacheName,
            $estimatedCachedTokens,
            $actualCacheTokens,
            $finalTokens,
            count($cachedMessageHashes)
        );

        return new CacheInfo(
            cacheName: $cacheName,
            isNewlyCreated: true,
            cacheWriteTokens: $finalTokens,
            cachedMessageHashes: $cachedMessageHashes
        );
    }

    /**
     * Save cache data to local storage.
     */
    private function saveCacheToLocalStorage(
        string $cacheKey,
        LocalCachedData $localCachedData,
        int $ttl
    ): void {
        $this->cache->set($cacheKey, $localCachedData->toArray(), $ttl);
    }

    /**
     * Log cache operation success.
     */
    private function logCacheOperationSuccess(
        string $prefix,
        string $cacheName,
        int $estimatedTokens,
        ?int $actualTokens,
        int $finalTokens,
        int $cachedMessageCount
    ): void {
        $this->logger?->info($prefix . ' successfully', [
            'cache_name' => $cacheName,
            'estimated_tokens' => $estimatedTokens,
            'actual_tokens' => $actualTokens,
            'final_tokens' => $finalTokens,
            'cached_message_count' => $cachedMessageCount,
            'source' => $actualTokens !== null ? 'api' : 'estimated',
        ]);
    }

    /**
     * Delete old cache (async operation, don't block on failure).
     */
    private function deleteOldCache(string $oldCacheName): void
    {
        try {
            $this->cacheClient->deleteCache($oldCacheName);
            $this->logger?->debug('Deleted old cache', ['cache_name' => $oldCacheName]);
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to delete old cache', [
                'cache_name' => $oldCacheName,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

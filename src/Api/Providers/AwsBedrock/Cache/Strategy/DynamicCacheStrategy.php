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

namespace Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy;

use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\SystemMessage;
use Psr\SimpleCache\CacheInterface;

class DynamicCacheStrategy implements CacheStrategyInterface
{
    private CacheInterface $cache;

    public function __construct(
        CacheInterface $cache
    ) {
        $this->cache = $cache;
    }

    public function apply(AutoCacheConfig $autoCacheConfig, ChatCompletionRequest $request): void
    {
        $messages = $request->getMessages();
        if (empty($messages)) {
            return;
        }
        $dynamicMessageCacheManager = $this->createDynamicMessageCacheManager($request);

        // 以此判定为同一轮对话
        $cacheKey = $dynamicMessageCacheManager->getCacheKey($request->getModel());

        $cachedData = $this->cache->get($cacheKey);
        /** @var null|DynamicMessageCacheManager $lastDynamicMessageCacheManager */
        $lastDynamicMessageCacheManager = $cachedData['dynamic_message_cache_manager'] ?? null;

        // 比对消息，如果前缀一致(历史消息全部匹配成功)，则进行上次的缓存点加载。如果不一致，则不进行缓存点加载
        if ($lastDynamicMessageCacheManager) {
            $dynamicMessageCacheManager->loadHistoryCachePoint($lastDynamicMessageCacheManager);
        }

        $this->addFixedCachePointIndex($dynamicMessageCacheManager, $autoCacheConfig);

        $incrementalTokens = $dynamicMessageCacheManager->calculateTotalTokens(
            $dynamicMessageCacheManager->getLastCachePointIndex() + 1,
            $dynamicMessageCacheManager->getLastMessageIndex()
        );
        if ($incrementalTokens >= $autoCacheConfig->getRefreshPointMinTokens()) {
            $lastIndex = $dynamicMessageCacheManager->getLastMessageIndex();
            $dynamicMessageCacheManager->addCachePointIndex($lastIndex);
        }

        // 重置缓存点
        $dynamicMessageCacheManager->resetPointIndex($autoCacheConfig->getMaxCachePoints());

        $cacheData = [
            'dynamic_message_cache_manager' => $dynamicMessageCacheManager,
        ];
        $this->cache->set($cacheKey, $cacheData, 7200);
    }

    private function addFixedCachePointIndex(DynamicMessageCacheManager $dynamicMessageCacheManager, AutoCacheConfig $autoCacheConfig): void
    {
        // 看一下 tools+system 是否标记了缓存点，固定机位
        if (! in_array(0, $dynamicMessageCacheManager->getCachePointMessages(), true)
            && ! in_array(1, $dynamicMessageCacheManager->getCachePointMessages(), true)) {
            // 观察是否需要标记
            if ($dynamicMessageCacheManager->getToolTokens() + $dynamicMessageCacheManager->getSystemTokens() >= $autoCacheConfig->getMinCacheTokens()) {
                // 如果都有，则添加到 system
                if ($dynamicMessageCacheManager->getToolTokens() > 0 && $dynamicMessageCacheManager->getSystemTokens() > 0) {
                    $dynamicMessageCacheManager->addCachePointIndex(1);
                }
                // 如果 system 为空，tools 不为空，那么缓存加到 tools
                if ($dynamicMessageCacheManager->getSystemTokens() <= 0 && $dynamicMessageCacheManager->getToolTokens() > 0) {
                    $dynamicMessageCacheManager->addCachePointIndex(0);
                }
                // 如果 tools 为空，system 不为空，那么缓存加到 system
                if ($dynamicMessageCacheManager->getToolTokens() <= 0 && $dynamicMessageCacheManager->getSystemTokens() > 0) {
                    $dynamicMessageCacheManager->addCachePointIndex(1);
                }
            }
        }
    }

    private function createDynamicMessageCacheManager(ChatCompletionRequest $request): DynamicMessageCacheManager
    {
        $index = 2;
        // tools 也当做是一个消息
        $cachePointMessages[0] = new CachePointMessage($request->getTools(), $request->getToolsTokenEstimate() ?? 0);
        foreach ($request->getMessages() as $message) {
            if ($message instanceof SystemMessage) {
                $cachePointMessages[1] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
            } else {
                $cachePointMessages[$index] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
                ++$index;
            }
        }

        return new DynamicMessageCacheManager($cachePointMessages);
    }
}

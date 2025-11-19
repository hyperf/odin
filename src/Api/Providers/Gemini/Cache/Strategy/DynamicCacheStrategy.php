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

use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheClient;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\RequestHandler;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Utils\ToolUtil;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Throwable;

/**
 * Dynamic cache strategy - applies caching based on conversation continuity and token thresholds.
 */
class DynamicCacheStrategy implements CacheStrategyInterface
{
    private CacheInterface $cache;

    private GeminiCacheClient $cacheClient;

    private ?LoggerInterface $logger;

    public function __construct(
        CacheInterface $cache,
        GeminiCacheClient $cacheClient,
        ?LoggerInterface $logger = null
    ) {
        $this->cache = $cache;
        $this->cacheClient = $cacheClient;
        $this->logger = $logger;
    }

    /**
     * 应用缓存策略（请求前）：检查是否有缓存可以使用.
     * 无需估算 token，直接根据前缀 hash 匹配检查是否有可用缓存.
     *
     * @return null|array 缓存信息，包含 cache_name, has_system, has_tools, has_first_user_message
     */
    public function apply(GeminiCacheConfig $config, ChatCompletionRequest $request): ?array
    {
        $messages = $request->getMessages();
        if (empty($messages)) {
            return null;
        }

        // 1. 创建消息缓存管理器（不需要 token 估算，只需要 hash）
        $messageCacheManager = $this->createMessageCacheManagerWithoutTokens($request);

        // 2. 从本地缓存获取上次的缓存信息
        $cacheKey = $messageCacheManager->getCacheKey($request->getModel());
        $cachedData = $this->cache->get($cacheKey);
        /** @var null|GeminiMessageCacheManager $lastMessageCacheManager */
        $lastMessageCacheManager = $cachedData['message_cache_manager'] ?? null;

        // 3. 检查是否有可用的缓存
        if (! $lastMessageCacheManager) {
            // 没有缓存，返回 null，请求正常发送
            return null;
        }

        // 4. 判断对话连续性（通过前缀 hash 匹配）
        if ($messageCacheManager->isContinuousConversation($lastMessageCacheManager, $request->getModel())) {
            // 对话连续，使用现有缓存
            $cacheName = $cachedData['cache_name'] ?? null;
            if ($cacheName) {
                $cachedMessageCount = $cachedData['cached_message_count'] ?? 0;
                return $this->buildCacheInfo($cacheName, $request, $cachedMessageCount > 0);
            }
        }

        // 对话不连续或没有缓存名称，返回 null，请求正常发送
        return null;
    }

    /**
     * 请求成功后创建或更新缓存.
     *
     * @param GeminiCacheConfig $config 缓存配置
     * @param ChatCompletionRequest $request 请求对象
     */
    public function createOrUpdateCache(GeminiCacheConfig $config, ChatCompletionRequest $request): void
    {
        $messages = $request->getMessages();
        if (empty($messages)) {
            return;
        }

        // 1. 计算 Token 估算
        $request->calculateTokenEstimates();

        // 2. 创建消息缓存管理器
        $messageCacheManager = $this->createMessageCacheManager($request);

        // 3. 计算前缀 hash
        $prefixHash = $messageCacheManager->getPrefixHash($request->getModel());

        // 4. 从本地缓存获取上次的缓存信息
        $cacheKey = $messageCacheManager->getCacheKey($request->getModel());
        $cachedData = $this->cache->get($cacheKey);
        /** @var null|GeminiMessageCacheManager $lastMessageCacheManager */
        $lastMessageCacheManager = $cachedData['message_cache_manager'] ?? null;

        // 5. 判断是否需要创建或移动缓存
        if ($lastMessageCacheManager && $messageCacheManager->isContinuousConversation($lastMessageCacheManager, $request->getModel())) {
            // 对话连续，检查是否需要移动缓存点
            $this->processCachePointMovement($config, $request, $cachedData, $messageCacheManager, $cacheKey, $prefixHash);
        } else {
            // 对话不连续，检查是否需要创建新缓存
            $this->processCacheCreation($config, $request, $messageCacheManager, $cacheKey, $prefixHash);
        }
    }

    /**
     * 处理缓存点移动（请求后调用）.
     * 检查增量 tokens，如果达到阈值则移动缓存点.
     */
    private function processCachePointMovement(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        array $cachedData,
        GeminiMessageCacheManager $messageCacheManager,
        string $cacheKey,
        string $prefixHash
    ): void {
        $cacheName = $cachedData['cache_name'] ?? null;
        if (! $cacheName) {
            // 没有缓存名称，尝试创建新缓存
            $this->processCacheCreation($config, $request, $messageCacheManager, $cacheKey, $prefixHash);
            return;
        }

        // 计算增量 tokens（从缓存点之后到倒数第二个消息）
        $cachedMessageCount = $cachedData['cached_message_count'] ?? 0;
        $startIndex = $cachedMessageCount > 0 ? 3 : 2; // 如果之前缓存了第一个 user message，从索引 3 开始
        $lastIndex = $messageCacheManager->getLastMessageIndex();

        // 移动缓存点时，需要保留最后一个消息不缓存，所以计算到倒数第二个消息
        $endIndex = $lastIndex > $startIndex ? $lastIndex - 1 : $lastIndex;
        $incrementalTokens = $messageCacheManager->calculateTotalTokens($startIndex, $endIndex);

        // 判断是否需要移动缓存点
        if ($incrementalTokens >= $config->getRefreshPointMinTokens() && $lastIndex > $startIndex) {
            // 移动缓存点（缓存到倒数第二个消息，最后一个消息正常发送）
            $this->moveCachePoint($config, $request, $cachedData, $messageCacheManager, $cacheKey, $prefixHash);
        }
    }

    /**
     * 处理缓存创建（请求后调用）.
     * 检查是否满足创建条件，如果满足则创建缓存.
     */
    private function processCacheCreation(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        GeminiMessageCacheManager $messageCacheManager,
        string $cacheKey,
        string $prefixHash
    ): void {
        // 计算基础前缀 tokens（只包含 system + tools，不包含第一个 user message）
        $basePrefixTokens = $messageCacheManager->getBasePrefixTokens();

        // 获取模型的最小缓存 tokens 阈值
        $minCacheTokens = GeminiCacheConfig::getMinCacheTokensByModel($request->getModel());
        // 如果配置的阈值更大，使用配置的值
        $minCacheTokens = max($minCacheTokens, $config->getMinCacheTokens());

        // 判断是否满足创建条件
        if ($basePrefixTokens < $minCacheTokens) {
            // 不满足条件，不创建缓存
            return;
        }

        // 创建缓存（第一次创建只缓存 tools + system，不包含第一个 user message）
        try {
            $cacheName = $this->createCache($config, $request, $messageCacheManager, true);

            // 保存缓存信息
            $this->cache->set($cacheKey, [
                'message_cache_manager' => $messageCacheManager,
                'prefix_hash' => $prefixHash,
                'cache_name' => $cacheName,
                'cached_message_count' => 0, // 第一次创建缓存，只缓存 tools + system，没有消息
                'created_at' => time(),
            ], $config->getTtl());
        } catch (Throwable $e) {
            // 缓存创建失败，记录日志但不影响请求
            $this->logger?->warning('Failed to create Gemini cache after request', [
                'error' => $e->getMessage(),
                'model' => $request->getModel(),
            ]);
        }
    }

    /**
     * 移动缓存点（请求后调用）.
     * 缓存从旧缓存点之后到倒数第二个消息，最后一个消息正常发送.
     */
    private function moveCachePoint(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        array $oldCacheData,
        GeminiMessageCacheManager $messageCacheManager,
        string $cacheKey,
        string $prefixHash
    ): void {
        // 1. 删除旧缓存
        $oldCacheName = $oldCacheData['cache_name'] ?? null;
        if ($oldCacheName) {
            try {
                $this->cacheClient->deleteCache($oldCacheName);
            } catch (Throwable $e) {
                // 记录日志，但不影响后续流程
                $this->logger?->warning('Failed to delete old Gemini cache', [
                    'error' => $e->getMessage(),
                    'cache_name' => $oldCacheName,
                ]);
            }
        }

        // 2. 创建新缓存（从旧缓存点之后到倒数第二个消息）
        // 最后一个消息需要正常发送，不缓存
        try {
            $newCacheName = $this->createCache($config, $request, $messageCacheManager, false, $oldCacheData);

            // 计算缓存的消息数量
            $cachedMessageCount = $oldCacheData['cached_message_count'] ?? 0;
            $startIndex = $cachedMessageCount > 0 ? 3 : 2;
            $lastIndex = $messageCacheManager->getLastMessageIndex();
            $endIndex = $lastIndex > $startIndex ? $lastIndex - 1 : $lastIndex;
            $newCachedMessageCount = max(0, $endIndex - $startIndex + 1);

            // 保存缓存信息
            $this->cache->set($cacheKey, [
                'message_cache_manager' => $messageCacheManager,
                'prefix_hash' => $prefixHash,
                'cache_name' => $newCacheName,
                'cached_message_count' => $newCachedMessageCount,
                'created_at' => time(),
            ], $config->getTtl());
        } catch (Throwable $e) {
            // 创建失败，记录日志但不影响请求
            $this->logger?->warning('Failed to create new Gemini cache after moving cache point', [
                'error' => $e->getMessage(),
                'model' => $request->getModel(),
            ]);
        }
    }

    /**
     * 创建缓存.
     *
     * @param bool $isFirstCache 是否是第一次创建缓存（只缓存 tools + system）
     * @param null|array $oldCachedData 旧缓存数据（移动缓存点时使用）
     */
    private function createCache(GeminiCacheConfig $config, ChatCompletionRequest $request, GeminiMessageCacheManager $messageCacheManager, bool $isFirstCache = false, ?array $oldCachedData = null): string
    {
        $model = $request->getModel();
        $cacheConfig = [];

        // 1. 添加 system_instruction（如果存在）
        $systemMessage = $this->getSystemMessage($request);
        if ($systemMessage) {
            $systemText = $systemMessage->getContent();
            if (! empty($systemText)) {
                $cacheConfig['system_instruction'] = [
                    'parts' => [
                        ['text' => $systemText],
                    ],
                ];
            }
        }

        // 2. 添加 tools（如果存在）
        $tools = $request->getTools();
        if (! empty($tools)) {
            $convertedTools = RequestHandler::convertTools($tools);
            if (! empty($convertedTools)) {
                $cacheConfig['tools'] = $convertedTools;
            }
        }

        // 3. 添加消息内容
        if ($isFirstCache) {
            // 第一次创建缓存：只缓存 tools + system，不包含第一个 user message
            $cacheConfig['contents'] = [];
        } else {
            // 移动缓存点：缓存从旧缓存点之后到倒数第二个消息
            $cachedMessageCount = $oldCachedData['cached_message_count'] ?? 0;
            // 第一次创建缓存时 cached_message_count 为 0（只缓存 tools + system）
            // 如果 cached_message_count > 0，说明之前缓存了第一个 user message，从索引 3 开始
            // 否则从索引 2 开始（第一个 user message）
            $startIndex = $cachedMessageCount > 0 ? 3 : 2;
            $lastIndex = $messageCacheManager->getLastMessageIndex();
            $endIndex = $lastIndex > $startIndex ? $lastIndex - 1 : $lastIndex; // 倒数第二个消息

            // 从 request 中提取需要缓存的消息范围
            $allMessages = $request->getMessages();
            $messagesToCache = [];

            // 跳过 system message（已经在 system_instruction 中）
            // 需要找到对应索引的消息
            $cachePointMessages = $messageCacheManager->getCachePointMessages();
            $messageIndex = 0; // 在 allMessages 中的索引（不包括 system）

            foreach ($allMessages as $message) {
                if ($message instanceof SystemMessage) {
                    continue; // 跳过 system message
                }

                // 找到当前消息在 cachePointMessages 中的索引
                $cacheIndex = null;
                for ($i = 2; $i <= $lastIndex; ++$i) {
                    if (isset($cachePointMessages[$i]) && $cachePointMessages[$i]->getOriginMessage() === $message) {
                        $cacheIndex = $i;
                        break;
                    }
                }

                if ($cacheIndex !== null && $cacheIndex >= $startIndex && $cacheIndex <= $endIndex) {
                    $messagesToCache[] = $message;
                }
            }

            if (empty($messagesToCache)) {
                throw new RuntimeException('Cannot create cache: no messages to cache');
            }

            // 使用 RequestHandler 转换消息
            $result = RequestHandler::convertMessages($messagesToCache);
            $cacheConfig['contents'] = $result['contents'];
        }

        // 4. 设置 TTL
        $cacheConfig['ttl'] = $config->getTtl() . 's';

        // 5. 调用 API 创建缓存
        return $this->cacheClient->createCache($model, $cacheConfig);
    }

    /**
     * 构建缓存信息.
     *
     * @param bool $hasFirstUserMessage 是否包含第一个 user message（第一次创建缓存时为 false）
     * @return array 缓存信息，包含 cache_name, has_system, has_tools, has_first_user_message
     */
    private function buildCacheInfo(string $cacheName, ChatCompletionRequest $request, bool $hasFirstUserMessage = true): array
    {
        return [
            'cache_name' => $cacheName,
            'has_system' => $this->getSystemMessage($request) !== null,
            'has_tools' => ! empty($request->getTools()),
            'has_first_user_message' => $hasFirstUserMessage && $this->getFirstUserMessage($request) !== null,
        ];
    }

    /**
     * 创建消息缓存管理器（需要 token 估算）.
     */
    private function createMessageCacheManager(ChatCompletionRequest $request): GeminiMessageCacheManager
    {
        // 确保 token 已估算
        $request->calculateTokenEstimates();

        return $this->createMessageCacheManagerWithoutTokens($request);
    }

    /**
     * 创建消息缓存管理器（不需要 token 估算，仅用于 hash 匹配）.
     */
    private function createMessageCacheManagerWithoutTokens(ChatCompletionRequest $request): GeminiMessageCacheManager
    {
        $index = 2;
        // tools 也当做是一个消息（索引 0）
        $toolsArray = ToolUtil::filter($request->getTools());
        $cachePointMessages[0] = new CachePointMessage($toolsArray, $request->getToolsTokenEstimate() ?? 0);

        // system message（索引 1）
        foreach ($request->getMessages() as $message) {
            if ($message instanceof SystemMessage) {
                $cachePointMessages[1] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
                break;
            }
        }

        // 其他消息（索引 2+）
        foreach ($request->getMessages() as $message) {
            if (! $message instanceof SystemMessage) {
                $cachePointMessages[$index] = new CachePointMessage($message, $message->getTokenEstimate() ?? 0);
                ++$index;
            }
        }

        return new GeminiMessageCacheManager($cachePointMessages);
    }

    /**
     * 获取 system message.
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
     * 获取第一个 user message.
     */
    private function getFirstUserMessage(ChatCompletionRequest $request): ?UserMessage
    {
        foreach ($request->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                return $message;
            }
        }
        return null;
    }
}

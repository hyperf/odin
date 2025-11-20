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
     * @return null|array 缓存信息，包含 cache_name, has_system, has_tools, cached_message_count
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
                return $this->buildCacheInfo($cacheName, $request, $cachedMessageCount);
            }
        }

        // 对话不连续或没有缓存名称，返回 null，请求正常发送
        return null;
    }

    /**
     * 请求成功后创建或更新缓存.
     * 简化逻辑：
     * - 如果前缀匹配（对话连续），检查增量 tokens 是否达到更新阈值，如果达到则创建新缓存
     * - 如果没有缓存或前缀不匹配，且满足条件则创建新缓存（缓存所有最新消息），并删除旧缓存.
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

        // 5. 如果前缀匹配（对话连续），检查是否需要更新缓存
        if ($lastMessageCacheManager && $messageCacheManager->isContinuousConversation($lastMessageCacheManager, $request->getModel())) {
            // 检查增量 tokens 是否达到更新阈值
            if ($this->shouldUpdateCache($config, $request, $cachedData, $messageCacheManager)) {
                // 达到阈值，删除旧缓存并创建新缓存
                $this->createCacheIfNeeded($config, $request, $messageCacheManager, $cacheKey, $prefixHash, $cachedData);
            }
            // 未达到阈值或已更新，直接返回（Gemini 的前缀缓存会自动匹配）
            return;
        }

        // 6. 没有缓存或前缀不匹配，检查是否需要创建新缓存
        $this->createCacheIfNeeded($config, $request, $messageCacheManager, $cacheKey, $prefixHash, $cachedData);
    }

    /**
     * 判断是否需要更新缓存（前缀匹配时）.
     * 检查增量 tokens 是否达到更新阈值.
     */
    private function shouldUpdateCache(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        array $cachedData,
        GeminiMessageCacheManager $messageCacheManager
    ): bool {
        $cacheName = $cachedData['cache_name'] ?? null;
        if (! $cacheName) {
            // 没有缓存名称，需要创建新缓存
            return true;
        }

        // 获取本次的 total tokens
        $currentTotalTokens = $request->getTotalTokenEstimate();
        if ($currentTotalTokens === null) {
            // 如果没有 total tokens，无法判断，不更新缓存
            return false;
        }

        // 获取上次的 total tokens
        $lastTotalTokens = $cachedData['total_tokens'] ?? null;
        if ($lastTotalTokens === null) {
            // 如果没有上次的 total tokens，需要创建新缓存
            return true;
        }

        // 计算增量 tokens：本次 total - 上次 total
        $incrementalTokens = $currentTotalTokens - $lastTotalTokens;

        // 如果增量小于等于 0，不需要更新
        if ($incrementalTokens <= 0) {
            return false;
        }

        // 判断是否达到更新阈值
        return $incrementalTokens >= $config->getRefreshPointMinTokens();
    }

    /**
     * 创建缓存（如果没有缓存或前缀不匹配时调用）.
     * 检查是否满足创建条件，如果满足则创建新缓存（缓存所有最新消息），并删除旧缓存.
     */
    private function createCacheIfNeeded(
        GeminiCacheConfig $config,
        ChatCompletionRequest $request,
        GeminiMessageCacheManager $messageCacheManager,
        string $cacheKey,
        string $prefixHash,
        ?array $oldCachedData
    ): void {
        // 计算基础前缀 tokens（只包含 system + tools，用于判断是否满足最小缓存阈值）
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

        // 创建新缓存（先创建再删除旧缓存，避免短暂无缓存的情况）
        $newCacheName = null;
        try {
            // 构建缓存配置
            $cacheConfig = $this->buildCacheConfig($config, $request);
            $model = $request->getModel();
            $newCacheName = $this->cacheClient->createCache($model, $cacheConfig);

            // 计算缓存的消息数量（只缓存了第一个 user message）
            $cachedMessageCount = 1; // 只缓存一个示例消息

            // 获取本次的 total tokens
            $totalTokens = $request->getTotalTokenEstimate() ?? 0;

            // 保存缓存信息
            $this->cache->set($cacheKey, [
                'message_cache_manager' => $messageCacheManager,
                'prefix_hash' => $prefixHash,
                'cache_name' => $newCacheName,
                'cached_message_count' => $cachedMessageCount,
                'total_tokens' => $totalTokens,
                'created_at' => time(),
            ], $config->getTtl());

            // 删除旧缓存（在新缓存创建成功后）
            $oldCacheName = $oldCachedData['cache_name'] ?? null;
            if ($oldCacheName && $oldCacheName !== $newCacheName) {
                try {
                    $this->cacheClient->deleteCache($oldCacheName);
                    $this->logger?->info('Deleted old Gemini cache after creating new cache', [
                        'old_cache_name' => $oldCacheName,
                        'new_cache_name' => $newCacheName,
                        'model' => $request->getModel(),
                    ]);
                } catch (Throwable $e) {
                    // 记录日志，但不影响主流程（旧缓存会自动过期）
                    $this->logger?->warning('Failed to delete old Gemini cache', [
                        'error' => $e->getMessage(),
                        'cache_name' => $oldCacheName,
                    ]);
                }
            }
        } catch (Throwable $e) {
            // 缓存创建失败，记录日志但不影响请求
            $this->logger?->warning('Failed to create Gemini cache after request', [
                'error' => $e->getMessage(),
                'model' => $request->getModel(),
            ]);
        }
    }

    /**
     * 构建缓存配置.
     * 构建用于创建缓存的配置数组.
     *
     * 注意：根据 Gemini Context Caching 最佳实践，应该只缓存稳定的上下文内容：
     * - system_instruction: 系统提示词
     * - tools: 工具定义
     * - contents: 只包含初始的示例消息（如果有）
     *
     * 不应该缓存会话历史，会话历史应通过正常的 contents 参数传递.
     */
    private function buildCacheConfig(GeminiCacheConfig $config, ChatCompletionRequest $request): array
    {
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

        // 3. 添加最小必要的 contents（只包含第一个 user message 作为示例）
        // 注意：根据 Gemini API 要求，缓存必须包含至少一个 content
        $firstUserMessage = $this->getFirstUserMessage($request);
        if ($firstUserMessage) {
            $convertedMessage = RequestHandler::convertUserMessage($firstUserMessage);
            $cacheConfig['contents'] = [$convertedMessage];
        } else {
            // 如果没有 user message，使用一个占位符
            $cacheConfig['contents'] = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'Hello'],
                    ],
                ],
            ];
        }

        // 4. 设置 TTL（验证范围：60s - 86400s）
        $ttl = $config->getTtl();
        // Ensure TTL is within valid range (60 seconds to 24 hours)
        $ttl = max(60, min(86400, $ttl));
        $cacheConfig['ttl'] = $ttl . 's';

        return $cacheConfig;
    }

    /**
     * 构建缓存信息.
     *
     * @param int $cachedMessageCount 已缓存的消息数量（不包括 system message）
     * @return array 缓存信息，包含 cache_name, has_system, has_tools, cached_message_count
     */
    private function buildCacheInfo(string $cacheName, ChatCompletionRequest $request, int $cachedMessageCount): array
    {
        return [
            'cache_name' => $cacheName,
            'has_system' => $this->getSystemMessage($request) !== null,
            'has_tools' => ! empty($request->getTools()),
            'cached_message_count' => $cachedMessageCount,
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

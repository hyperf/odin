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

use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\CacheStrategyInterface;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\DynamicCacheStrategy;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\NoneCacheStrategy;
use Hyperf\Odin\Api\Providers\Gemini\GeminiConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

use function Hyperf\Support\make;

/**
 * Gemini 缓存管理器（核心类）.
 * 负责缓存策略的配置和管理.
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
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->apiOptions = $apiOptions;
        $this->geminiConfig = $geminiConfig;
        $this->logger = $logger;
    }

    /**
     * 检查是否有缓存可以使用（请求前调用）.
     * 无需估算 token，直接根据规则检查是否有可用缓存.
     *
     * @param ChatCompletionRequest $request 请求对象
     * @return null|array 缓存信息，包含 cache_name, has_system, has_tools, cached_message_count，如果没有缓存则返回 null
     */
    public function checkCache(ChatCompletionRequest $request): ?array
    {
        // 1. 选择策略（根据配置选择，不依赖 token 估算）
        $strategy = $this->selectStrategy($request);

        // 2. 检查缓存（不创建，只检查是否有可用的缓存）
        return $strategy->apply($this->config, $request);
    }

    /**
     * 请求成功后创建或更新缓存（请求后调用）.
     *
     * @param ChatCompletionRequest $request 请求对象
     */
    public function createOrUpdateCacheAfterRequest(ChatCompletionRequest $request): void
    {
        // 1. 如果还没有实际的 tokens（从 usage 获取），则进行估算
        // 优先使用实际的 tokens，如果没有才估算
        if ($request->getTotalTokenEstimate() === null) {
            $request->calculateTokenEstimates();
        }

        // 2. 选择策略（需要 token 检查）
        $strategy = $this->selectStrategy($request, true);

        // 3. 创建或更新缓存
        $strategy->createOrUpdateCache($this->config, $request);
    }

    /**
     * 根据请求内容选择缓存策略.
     * 对于 checkCache，总是使用 DynamicCacheStrategy（不依赖 token 估算）.
     * 对于 handleAfterRequest，需要根据 token 判断是否创建缓存.
     */
    private function selectStrategy(ChatCompletionRequest $request, bool $needTokenCheck = false): CacheStrategyInterface
    {
        // 如果需要 token 检查（创建缓存时），才进行 token 判断
        if ($needTokenCheck) {
            $totalTokens = $request->getTotalTokenEstimate();
            if ($totalTokens === null || $totalTokens < $this->config->getMinCacheTokens()) {
                return $this->createStrategy(NoneCacheStrategy::class);
            }
        }
        return $this->createStrategy(DynamicCacheStrategy::class);
    }

    /**
     * 创建策略实例，使用DI容器自动注入依赖.
     */
    private function createStrategy(string $strategyClass): CacheStrategyInterface
    {
        // If we have apiOptions and geminiConfig, manually create the strategy with proper dependencies
        if ($this->apiOptions !== null && $this->geminiConfig !== null) {
            $cache = ApplicationContext::getContainer()->get(CacheInterface::class);
            $cacheClient = new GeminiCacheClient($this->geminiConfig, $this->apiOptions, $this->logger);
            return new $strategyClass($cache, $cacheClient, $this->logger);
        }

        // Otherwise, use DI container (will use default ApiOptions if not provided)
        return make($strategyClass);
    }
}

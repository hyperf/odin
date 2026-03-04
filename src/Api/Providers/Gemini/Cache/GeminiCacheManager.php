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
        // Create cache client, may return null if configuration is invalid
        $cacheClient = $this->createCacheClient($request);

        if (! $cacheClient) {
            // Configuration invalid, cache disabled
            return null;
        }

        // Use conversation cache strategy
        $strategy = new ConversationCacheStrategy($cacheClient, $this->logger);
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
     * Create appropriate cache client based on configuration.
     * 根据配置创建合适的缓存客户端实现.
     *
     * @return null|GeminiCacheClientInterface 返回 null 表示配置不完整，缓存功能禁用
     */
    private function createCacheClient(ChatCompletionRequest $request): ?GeminiCacheClientInterface
    {
        $model = $request->getModel();

        // 判断是否是 Vertex AI 请求
        $isVertexAiRequest = $this->isVertexAiRequest($model);

        if ($isVertexAiRequest) {
            // Vertex AI 请求，需要检查 Service Account 配置
            $serviceAccountConfig = $this->geminiConfig?->getServiceAccountConfig();

            if (! $serviceAccountConfig) {
                // 配置缺失，记录日志并返回 null
                $this->logger?->warning('Vertex AI cache disabled: Service Account configuration is missing', [
                    'model' => $model,
                ]);
                return null;
            }

            // 使用 Vertex AI Cache Client (Service Account 认证)
            return new VertexAiCacheClient(
                $this->geminiConfig,
                $serviceAccountConfig,
                $this->apiOptions,
                $this->logger
            );
        }

        // Generative Language API 请求，使用 Gemini Cache Client (API Key 认证)
        return new GeminiCacheClient($this->geminiConfig, $this->apiOptions, $this->logger);
    }

    /**
     * 判断是否是 Vertex AI 请求.
     *
     * @param string $model 模型名称
     */
    private function isVertexAiRequest(string $model): bool
    {
        // 检查模型格式是否是 Vertex AI 格式
        // 1. projects/{project}/locations/{location}/publishers/*/models/*
        if (str_starts_with($model, 'projects/')) {
            return true;
        }

        // 2. 检查 base_url 是否包含 aiplatform.googleapis.com
        $baseUrl = $this->geminiConfig?->getBaseUrl() ?? '';
        if (str_contains($baseUrl, 'aiplatform.googleapis.com')) {
            return true;
        }

        return false;
    }
}

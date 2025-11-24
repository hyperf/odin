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

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeAutoCacheConfig;
use Hyperf\Odin\Api\Providers\DashScope\DashScope;
use Hyperf\Odin\Api\Providers\DashScope\DashScopeConfig;
use Hyperf\Odin\Contract\Api\ClientInterface;

/**
 * DashScope 模型实现
 * 基于现有 CachePoint 架构支持确定缓存.
 */
class DashScopeModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    protected function getClient(): ClientInterface
    {
        $config = $this->config;
        $this->processApiBaseUrl($config);

        $dashScope = new DashScope();

        // 创建自动缓存配置
        $autoCacheConfig = $this->createAutoCacheConfig($config);

        $configObj = new DashScopeConfig(
            apiKey: $config['api_key'] ?? '',
            baseUrl: $config['base_url'] ?? 'https://dashscope.aliyuncs.com',
            skipApiKeyValidation: $config['skip_api_key_validation'] ?? false,
            autoCacheConfig: $autoCacheConfig
        );

        return $dashScope->getClient($configObj, $this->getApiRequestOptions(), $this->logger);
    }

    /**
     * 创建自动缓存配置.
     */
    private function createAutoCacheConfig(array $config): DashScopeAutoCacheConfig
    {
        $cacheConfig = $config['auto_cache_config'] ?? [];

        return new DashScopeAutoCacheConfig(
            minCacheTokens: $cacheConfig['min_cache_tokens'] ?? 1024,
            supportedModels: $cacheConfig['supported_models'] ?? ['qwen3-coder-plus'],
            autoEnabled: $cacheConfig['auto_enabled'] ?? false
        );
    }
}

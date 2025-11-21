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

namespace Hyperf\Odin\Factory;

use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrock;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsType;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeAutoCacheConfig;
use Hyperf\Odin\Api\Providers\DashScope\DashScope;
use Hyperf\Odin\Api\Providers\DashScope\DashScopeConfig;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\Gemini;
use Hyperf\Odin\Api\Providers\Gemini\GeminiConfig;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAI;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Contract\Api\ClientInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 客户端工厂类，负责创建LLM API客户端实例.
 */
class ClientFactory
{
    /**
     * 创建OpenAI客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createOpenAIClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? '';
        $organization = $config['organization'] ?? '';

        // 创建配置对象
        $clientConfig = new OpenAIConfig(
            apiKey: $apiKey,
            organization: $organization,
            baseUrl: $baseUrl
        );

        // 创建API实例
        $openAI = new OpenAI();

        // 创建客户端
        return $openAI->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建Azure OpenAI客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createAzureOpenAIClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['api_base'] ?? $config['base_url'] ?? '';
        $apiVersion = $config['api_version'] ?? '2023-05-15';
        $deploymentName = $config['deployment_name'] ?? '';

        // 创建配置对象
        $clientConfig = new AzureOpenAIConfig(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            apiVersion: $apiVersion,
            deploymentName: $deploymentName
        );

        // 创建API实例
        $azureOpenAI = new AzureOpenAI();

        // 创建客户端
        return $azureOpenAI->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建AWS Bedrock客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createAwsBedrockClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $accessKey = $config['access_key'] ?? '';
        $secretKey = $config['secret_key'] ?? '';
        $region = $config['region'] ?? 'us-east-1';
        $type = $config['type'] ?? AwsType::CONVERSE_CUSTOM;
        $autoCache = (bool) ($config['auto_cache'] ?? false);
        $autoCacheConfig = null;
        if (isset($config['auto_cache_config'])) {
            $autoCacheConfig = new AutoCacheConfig(
                maxCachePoints: $config['auto_cache_config']['max_cache_points'] ?? 4,
                minCacheTokens: $config['auto_cache_config']['min_cache_tokens'] ?? 2048,
                refreshPointMinTokens: $config['auto_cache_config']['refresh_point_min_tokens'] ?? 5000,
                minHitCount: $config['auto_cache_config']['min_hit_count'] ?? 3,
            );
        }

        // 创建配置对象
        $clientConfig = new AwsBedrockConfig(
            accessKey: $accessKey,
            secretKey: $secretKey,
            region: $region,
            type: $type,
            autoCache: $autoCache,
            autoCacheConfig: $autoCacheConfig
        );

        // 如果未提供API选项，则创建一个默认的选项
        if ($apiOptions === null) {
            $apiOptions = new ApiOptions();
        }

        // 创建API实例 - 使用正确的类结构
        $awsBedrock = new AwsBedrock();

        // 创建客户端
        return $awsBedrock->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建DashScope客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createDashScopeClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? 'https://dashscope.aliyuncs.com';
        $skipApiKeyValidation = (bool) ($config['skip_api_key_validation'] ?? false);

        // 处理自动缓存配置
        $autoCacheConfig = null;
        if (isset($config['auto_cache_config'])) {
            $autoCacheConfig = new DashScopeAutoCacheConfig(
                minCacheTokens: $config['auto_cache_config']['min_cache_tokens'] ?? 1024,
                supportedModels: $config['auto_cache_config']['supported_models'] ?? ['qwen3-coder-plus', 'qwen-max', 'qwen-plus', 'qwen-turbo'],
                autoEnabled: (bool) ($config['auto_cache_config']['auto_enabled'] ?? false)
            );
        }

        // 创建配置对象
        $clientConfig = new DashScopeConfig(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            skipApiKeyValidation: $skipApiKeyValidation,
            autoCacheConfig: $autoCacheConfig
        );

        // 如果未提供API选项，则创建一个默认的选项
        if ($apiOptions === null) {
            $apiOptions = new ApiOptions();
        }

        // 创建API实例
        $dashScope = new DashScope();

        // 创建客户端
        return $dashScope->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建Gemini客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createGeminiClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
        $skipApiKeyValidation = (bool) ($config['skip_api_key_validation'] ?? false);

        // 处理自动缓存配置（统一缓存策略）
        $cacheConfig = null;
        if (isset($config['auto_cache_config'])) {
            $autoCacheConfig = $config['auto_cache_config'];

            $cacheConfig = new GeminiCacheConfig(
                enableCache: (bool) ($autoCacheConfig['enable_cache'] ?? false),
                minCacheTokens: $autoCacheConfig['min_cache_tokens'] ?? 4096,
                refreshThreshold: $autoCacheConfig['refresh_threshold'] ?? 8000,
                cacheTtl: $autoCacheConfig['cache_ttl'] ?? 600,
                estimationRatio: (float) ($autoCacheConfig['estimation_ratio'] ?? 0.33)
            );
        }

        // 创建配置对象
        $clientConfig = new GeminiConfig(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            skipApiKeyValidation: $skipApiKeyValidation
        );

        // 设置缓存配置
        if ($cacheConfig) {
            $clientConfig->setCacheConfig($cacheConfig);
        }

        // 创建API实例
        $gemini = new Gemini();

        // 由于 Gemini 模型的 chunk 是一大片一大片的通常需要更长的响应时间，调整API选项的超时设置
        $apiOptions->setStreamChunkTimeout($apiOptions->getStreamTotalTimeout());
        $apiOptions->setStreamFirstChunkTimeout($apiOptions->getStreamTotalTimeout());

        // 创建客户端
        return $gemini->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 根据提供商类型创建客户端.
     *
     * @param string $provider 提供商类型 (openai, azure_openai, aws_bedrock, dashscope, gemini)
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createClient(string $provider, array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        return match ($provider) {
            'openai' => self::createOpenAIClient($config, $apiOptions, $logger),
            'azure_openai' => self::createAzureOpenAIClient($config, $apiOptions, $logger),
            'aws_bedrock' => self::createAwsBedrockClient($config, $apiOptions, $logger),
            'dashscope' => self::createDashScopeClient($config, $apiOptions, $logger),
            'gemini' => self::createGeminiClient($config, $apiOptions, $logger),
            default => throw new InvalidArgumentException(sprintf('Unsupported provider: %s', $provider)),
        };
    }
}

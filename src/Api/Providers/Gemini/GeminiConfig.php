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

namespace Hyperf\Odin\Api\Providers\Gemini;

use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Contract\Api\ConfigInterface;

class GeminiConfig implements ConfigInterface
{
    public string $baseUrl;

    public string $apiKey;

    /**
     * Whether to skip API Key validation.
     */
    protected bool $skipApiKeyValidation = false;

    /**
     * Cache configuration.
     */
    protected ?GeminiCacheConfig $cacheConfig = null;

    /**
     * Service Account 配置 (用于 Vertex AI Platform API 认证).
     */
    protected ?ServiceAccountConfig $serviceAccountConfig = null;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        bool $skipApiKeyValidation = false,
        ?ServiceAccountConfig $serviceAccountConfig = null,
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->skipApiKeyValidation = $skipApiKeyValidation;
        $this->serviceAccountConfig = $serviceAccountConfig;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function shouldSkipApiKeyValidation(): bool
    {
        return $this->skipApiKeyValidation;
    }

    public function getServiceAccountConfig(): ?ServiceAccountConfig
    {
        return $this->serviceAccountConfig;
    }

    public function setServiceAccountConfig(?ServiceAccountConfig $serviceAccountConfig): void
    {
        $this->serviceAccountConfig = $serviceAccountConfig;
    }

    public static function fromArray(array $config): self
    {
        $serviceAccountConfig = null;
        if (isset($config['service_account'])) {
            // 如果提供了 service_account 配置数组，创建 ServiceAccountConfig
            $serviceAccountConfig = ServiceAccountConfig::fromArray($config['service_account']);
        } elseif (isset($config['service_account_key_path'])) {
            // 兼容：如果提供了文件路径，从文件加载
            $serviceAccountConfig = ServiceAccountConfig::fromFile($config['service_account_key_path']);
        }

        return new self(
            $config['api_key'] ?? '',
            $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta',
            $config['skip_api_key_validation'] ?? false,
            $serviceAccountConfig,
        );
    }

    public function toArray(): array
    {
        $array = [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'skip_api_key_validation' => $this->skipApiKeyValidation,
        ];

        if ($this->serviceAccountConfig) {
            $array['service_account'] = $this->serviceAccountConfig->toArray();
        }

        return $array;
    }

    public function isAutoCache(): bool
    {
        return $this->cacheConfig !== null && $this->cacheConfig->isEnableCache();
    }

    public function getCacheConfig(): ?GeminiCacheConfig
    {
        return $this->cacheConfig;
    }

    public function setCacheConfig(GeminiCacheConfig $cacheConfig): void
    {
        $this->cacheConfig = $cacheConfig;
    }
}

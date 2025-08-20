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

namespace Hyperf\Odin\Api\Providers\DashScope;

use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeAutoCacheConfig;
use Hyperf\Odin\Contract\Api\ConfigInterface;

class DashScopeConfig implements ConfigInterface
{
    private DashScopeAutoCacheConfig $autoCacheConfig;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://dashscope.aliyuncs.com',
        private readonly bool $skipApiKeyValidation = false,
        ?DashScopeAutoCacheConfig $autoCacheConfig = null
    ) {
        $this->autoCacheConfig = $autoCacheConfig ?? new DashScopeAutoCacheConfig();
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

    public function getAutoCacheConfig(): DashScopeAutoCacheConfig
    {
        return $this->autoCacheConfig;
    }

    public function isAutoCache(): bool
    {
        return $this->autoCacheConfig->isAutoEnabled();
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'skip_api_key_validation' => $this->skipApiKeyValidation,
        ];
    }
}

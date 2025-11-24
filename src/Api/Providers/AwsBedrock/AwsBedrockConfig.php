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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Contract\Api\ConfigInterface;

class AwsBedrockConfig implements ConfigInterface
{
    public function __construct(
        public string $accessKey,
        public string $secretKey,
        public string $region = 'us-east-1',
        /**
         * API type:
         * - converse_custom: Converse API without AWS SDK (custom Guzzle + SigV4) [default]
         * - converse: Converse API with AWS SDK
         * - invoke: InvokeModel API with AWS SDK
         *
         * @var string
         */
        public string $type = AwsType::CONVERSE_CUSTOM,
        public bool $autoCache = false,
        public ?AutoCacheConfig $autoCacheConfig = null,
    ) {
        if (! $this->autoCacheConfig) {
            $this->autoCacheConfig = new AutoCacheConfig();
        }
    }

    public function isAutoCache(): bool
    {
        return $this->autoCache;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAutoCacheConfig(): AutoCacheConfig
    {
        return $this->autoCacheConfig;
    }

    /**
     * AWS Bedrock 不使用 API Key，此方法是为了实现接口而提供.
     */
    public function getApiKey(): string
    {
        return '';
    }

    /**
     * AWS Bedrock 不使用 Base URL，此方法是为了实现接口而提供.
     */
    public function getBaseUrl(): string
    {
        return '';
    }

    public function toArray(): array
    {
        return [
            'access_key' => $this->accessKey,
            'secret_key' => $this->secretKey,
            'region' => $this->region,
            'type' => $this->type,
        ];
    }
}

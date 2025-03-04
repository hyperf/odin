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

use Hyperf\Odin\Contract\Api\ConfigInterface;

class AwsBedrockConfig implements ConfigInterface
{
    public function __construct(
        public string $accessKey,
        public string $secretKey,
        public string $region = 'us-east-1'
    ) {}

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
        ];
    }
}

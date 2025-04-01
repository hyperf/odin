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

namespace Hyperf\Odin\Api\Providers\AzureOpenAI;

use Hyperf\Odin\Contract\Api\ConfigInterface;

class AzureOpenAIConfig implements ConfigInterface
{
    public string $baseUrl;

    public string $apiKey;

    protected string $apiVersion;

    protected string $deploymentName;

    protected ?int $timeout;

    public function __construct(
        string $apiKey,
        string $baseUrl,
        string $apiVersion,
        string $deploymentName,
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->apiVersion = $apiVersion;
        $this->deploymentName = $deploymentName;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getDeploymentName(): string
    {
        return $this->deploymentName;
    }

    /**
     * 从配置数组创建配置对象
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['api_key'] ?? '',
            $config['api_base'] ?? '',
            $config['api_version'] ?? '2023-05-15',
            $config['deployment_name'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'api_base' => $this->baseUrl,
            'api_version' => $this->apiVersion,
            'deployment_name' => $this->deploymentName,
        ];
    }
}

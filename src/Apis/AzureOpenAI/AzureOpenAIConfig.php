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

namespace Hyperf\Odin\Apis\AzureOpenAI;

class AzureOpenAIConfig
{
    public function __construct(
        protected array $config = [],
    ) {

    }

    public function getApiKey(): ?string
    {
        return $this->config['api_key'] ?? null;
    }

    public function getBaseUrl(): string
    {
        return $this->config['api_base'] ?? '';
    }

    public function getApiVersion(): ?string
    {
        return $this->config['api_version'] ?? null;
    }

    public function getDeploymentName(): ?string
    {
        return $this->config['deployment_name'] ?? null;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}

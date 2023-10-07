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
        protected array $mapper = [],
    ) {

    }

    public function getApiKey(string $model): ?string
    {
        return $this->mapper[$model]['api_key'] ?? null;
    }

    public function getBaseUrl(string $model): string
    {
        return $this->mapper[$model]['api_base'] ?? '';
    }

    public function getApiVersion(string $model): ?string
    {
        return $this->mapper[$model]['api_version'] ?? null;
    }

    public function getDeploymentName(string $model): ?string
    {
        return $this->mapper[$model]['deployment_name'] ?? null;
    }

    public function getMapper(): array
    {
        return $this->mapper;
    }
}

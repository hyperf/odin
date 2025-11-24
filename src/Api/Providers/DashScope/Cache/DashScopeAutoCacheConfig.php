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

namespace Hyperf\Odin\Api\Providers\DashScope\Cache;

/**
 * DashScope 自动缓存配置
 * 参考 AWS Bedrock AutoCacheConfig 实现.
 */
class DashScopeAutoCacheConfig
{
    /**
     * 缓存点最小生效 tokens 阈值
     */
    private int $minCacheTokens;

    /**
     * 支持的模型列表.
     */
    private array $supportedModels;

    /**
     * 是否启用自动缓存.
     */
    private bool $autoEnabled;

    public function __construct(
        int $minCacheTokens = 1024,
        array $supportedModels = ['qwen3-coder-plus'],
        bool $autoEnabled = false
    ) {
        $this->minCacheTokens = $minCacheTokens;
        $this->supportedModels = $supportedModels;
        $this->autoEnabled = $autoEnabled;
    }

    public function getMinCacheTokens(): int
    {
        return $this->minCacheTokens;
    }

    public function getSupportedModels(): array
    {
        return $this->supportedModels;
    }

    public function isAutoEnabled(): bool
    {
        return $this->autoEnabled;
    }

    public function isModelSupported(string $model): bool
    {
        return in_array($model, $this->supportedModels);
    }
}

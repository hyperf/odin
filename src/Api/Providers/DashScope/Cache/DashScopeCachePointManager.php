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

use Hyperf\Odin\Api\Providers\DashScope\Cache\Strategy\AutoCacheStrategy;
use Hyperf\Odin\Api\Providers\DashScope\Cache\Strategy\DashScopeCacheStrategyInterface;
use Hyperf\Odin\Api\Providers\DashScope\Cache\Strategy\ManualCacheStrategy;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;

/**
 * DashScope 缓存点管理器
 * 参考 AwsBedrockCachePointManager 实现.
 */
class DashScopeCachePointManager
{
    private DashScopeAutoCacheConfig $autoCacheConfig;

    public function __construct(DashScopeAutoCacheConfig $autoCacheConfig)
    {
        $this->autoCacheConfig = $autoCacheConfig;
    }

    /**
     * 配置缓存点.
     *
     * @param ChatCompletionRequest $request 需要配置缓存点的请求对象（会直接修改此对象）
     */
    public function configureCachePoints(ChatCompletionRequest $request): void
    {
        // 1. 估算 Token（使用 ChatCompletionRequest 内的方法）
        $request->calculateTokenEstimates();

        // 2. 选择策略
        $strategy = $this->selectStrategy();

        // 3. 应用策略
        $strategy->apply($this->autoCacheConfig, $request);
    }

    /**
     * 选择缓存策略.
     */
    private function selectStrategy(): DashScopeCacheStrategyInterface
    {
        if ($this->autoCacheConfig->isAutoEnabled()) {
            return new AutoCacheStrategy();
        }

        return new ManualCacheStrategy();
    }
}

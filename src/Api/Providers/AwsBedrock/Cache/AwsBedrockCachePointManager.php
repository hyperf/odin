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

namespace Hyperf\Odin\Api\Providers\AwsBedrock\Cache;

use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy\CacheStrategyInterface;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy\DynamicCacheStrategy;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy\NoneCacheStrategy;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use HyperfTest\Odin\Mock\Cache;
use Throwable;

use function Hyperf\Support\make;

class AwsBedrockCachePointManager
{
    private AutoCacheConfig $autoCacheConfig;

    public function __construct(
        AutoCacheConfig $autoCacheConfig,
    ) {
        $this->autoCacheConfig = $autoCacheConfig;
    }

    /**
     * 分析请求并配置缓存点.
     *
     * @param ChatCompletionRequest $request 需要配置缓存点的请求对象 (会直接修改此对象)
     */
    public function configureCachePoints(ChatCompletionRequest $request): void
    {
        // 1. 重置现有设置 (如果需要，可以在这里调用 resetCachePoints)
        $this->resetCachePoints($request);

        // 2. 估算 Token (使用 ChatCompletionRequest 内的方法)
        $request->calculateTokenEstimates();

        // 3. 选择策略
        $strategy = $this->selectStrategy($request);

        // 4. 应用策略
        $strategy->apply($this->autoCacheConfig, $request);
    }

    /**
     * 根据请求内容选择缓存策略.
     */
    private function selectStrategy(ChatCompletionRequest $request): CacheStrategyInterface
    {
        $totalTokens = $request->getTotalTokenEstimate();
        if ($totalTokens < $this->autoCacheConfig->getMinCacheTokens()) {
            return $this->createStrategy(NoneCacheStrategy::class);
        }
        return $this->createStrategy(DynamicCacheStrategy::class);
    }

    /**
     * 创建策略实例，优先使用DI容器，失败时直接实例化.
     */
    private function createStrategy(string $strategyClass): CacheStrategyInterface
    {
        try {
            return make($strategyClass);
        } catch (Throwable $e) {
            // 在测试环境或无Swoole环境下，直接实例化
            if ($strategyClass === NoneCacheStrategy::class) {
                return new NoneCacheStrategy();
            }

            if ($strategyClass === DynamicCacheStrategy::class) {
                // DynamicCacheStrategy 需要 CacheInterface，使用模拟缓存
                $cache = new Cache();
                return new DynamicCacheStrategy($cache);
            }

            throw $e;
        }
    }

    /**
     * 重置请求对象上的缓存点设置.
     */
    private function resetCachePoints(ChatCompletionRequest $request): void
    {
        $request->setToolsCache(false);
        foreach ($request->getMessages() as $message) {
            $message->setCachePoint(null);
        }
    }
}

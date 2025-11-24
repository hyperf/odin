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

namespace Hyperf\Odin\Api\Providers\DashScope\Cache\Strategy;

use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeAutoCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;

/**
 * DashScope 手动缓存策略
 * 验证用户手动设置的缓存点，只保留最后一个满足条件的缓存点.
 */
class ManualCacheStrategy implements DashScopeCacheStrategyInterface
{
    public function apply(DashScopeAutoCacheConfig $config, ChatCompletionRequest $request): void
    {
        $messages = $request->getMessages();
        $validCachePointIndex = null;

        // 第一轮：找到最后一个满足条件的缓存点
        foreach ($messages as $index => $message) {
            $cachePoint = $message->getCachePoint();
            if ($cachePoint !== null && $cachePoint->getType() === 'ephemeral') {
                $isValid = true;

                // 检查模型支持
                if (! $config->isModelSupported($request->getModel())) {
                    $isValid = false;
                }

                // 检查 token 数量
                $messageTokens = $message->getTokenEstimate() ?? 0;
                if ($messageTokens < $config->getMinCacheTokens()) {
                    $isValid = false;
                }

                // 如果当前缓存点有效，记录其位置
                if ($isValid) {
                    $validCachePointIndex = $index;
                }
            }
        }

        // 第二轮：清除所有缓存点，只保留最后一个有效的
        foreach ($messages as $index => $message) {
            $cachePoint = $message->getCachePoint();
            if ($cachePoint !== null && $cachePoint->getType() === 'ephemeral') {
                // 只保留最后一个有效的缓存点，其他都移除
                if ($index !== $validCachePointIndex) {
                    $message->setCachePoint(null);
                }
            }
        }
    }
}

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
use Hyperf\Odin\Message\CachePoint;

/**
 * DashScope 自动缓存策略
 * 自动为最后一条消息添加缓存点.
 */
class AutoCacheStrategy implements DashScopeCacheStrategyInterface
{
    public function apply(DashScopeAutoCacheConfig $config, ChatCompletionRequest $request): void
    {
        // 1. 检查模型支持
        if (! $config->isModelSupported($request->getModel())) {
            return;
        }

        // 2. 检查 token 数量
        $totalTokens = $request->getTotalTokenEstimate();
        if ($totalTokens < $config->getMinCacheTokens()) {
            return;
        }

        // 3. 清除所有手动设置的缓存点，并为最后一条消息自动添加缓存点
        $messages = $request->getMessages();
        if (! empty($messages)) {
            // 清除所有消息的手动缓存点
            foreach ($messages as $message) {
                $message->setCachePoint(null);
            }

            // 为最后一条消息设置自动缓存点
            $lastMessage = end($messages);
            $cachePoint = new CachePoint('ephemeral');
            $lastMessage->setCachePoint($cachePoint);
        }
    }
}

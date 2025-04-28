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

namespace Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy;

use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Message\CachePoint;
use Hyperf\Odin\Message\SystemMessage;
use Psr\SimpleCache\CacheInterface;

class DynamicCacheStrategy implements CacheStrategyInterface
{
    private CacheInterface $cache;

    public function __construct(
        CacheInterface $cache
    ) {
        $this->cache = $cache;
    }

    public function apply(AutoCacheConfig $autoCacheConfig, ChatCompletionRequest $request): void
    {
        $messages = $request->getMessages();
        if (empty($messages)) {
            return;
        }

        // 永远规则：tools 是 0，system 是 1
        $messageList = [
            0 => null,
        ];
        $messageTokens = [
            0 => $request->getToolsTokenEstimate(),
        ];
        $index = 2;
        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                $messageList[1] = $message;
                $messageTokens[1] = $message->getTokenEstimate();
            } else {
                $messageList[$index] = $message;
                $messageTokens[$index] = $message->getTokenEstimate();
                ++$index;
            }
        }

        // 标记已设置的缓存点数量
        $usedCachePoints = 0;
        $cachePointIndex = [];

        $toolsTokens = $request->getToolsTokenEstimate() ?? 0;
        $systemTokens = $request->getSystemTokenEstimate() ?? 0;

        if ($toolsTokens + $systemTokens >= $autoCacheConfig->getMinCacheTokens()) {
            // 在 system 设置缓存点
            if (! $this->setCachePoint($autoCacheConfig, $messageList[1], $usedCachePoints)) {
                return;
            }
            $cachePointIndex[] = 1;
        }

        // 为其余消息动态分配缓存点
        if (count($messageList) <= 1) {
            return;
        }

        // 记录最后一个缓存点的索引
        $lastCachePointIndex = ! empty($cachePointIndex) ? max($cachePointIndex) : 0;

        // 计算从上一个缓存点到最后一条消息之间的token增量
        $incrementalTokens = 0;
        for ($i = $lastCachePointIndex + 1; $i < count($messageList); ++$i) {
            if (isset($messageList[$i], $messageTokens[$i])) {
                $incrementalTokens += $messageTokens[$i];
            }
        }

        // 获取最后一条消息
        $lastMessage = end($messages);

        // 如果增量token达到最小缓存token数才设置缓存点
        if ($incrementalTokens >= $autoCacheConfig->getMinCacheTokens()) {
            // 设置最后一条消息为缓存点
            $this->setCachePoint($autoCacheConfig, $lastMessage, $usedCachePoints);
        }
    }

    private function setCachePoint(AutoCacheConfig $autoCacheConfig, MessageInterface $message, int &$usedCachePoints): bool
    {
        if ($autoCacheConfig->getMaxCachePoints() <= $usedCachePoints) {
            return false;
        }

        $message->setCachePoint(new CachePoint());
        ++$usedCachePoints;
        return true;
    }
}

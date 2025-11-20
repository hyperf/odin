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

namespace Hyperf\Odin\Event;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 事件回调监听器.
 * 监听请求完成事件，执行事件中注册的回调函数.
 * 支持所有提供商的功能扩展（缓存、统计等）.
 */
#[Listener(priority: 1000)]
class EventCallbackListener implements ListenerInterface
{
    protected LoggerInterface $logger;

    public function __construct(protected ContainerInterface $container)
    {
        $this->logger = $this->container->get(LoggerInterface::class);
    }

    public function listen(): array
    {
        return [
            AfterChatCompletionsEvent::class,
            AfterChatCompletionsStreamEvent::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof AfterChatCompletionsEvent) {
            $this->handleCallbacks($event);
        }
    }

    /**
     * 执行事件中注册的回调函数.
     */
    public function handleCallbacks(AfterChatCompletionsEvent $event): void
    {
        // 执行事件中注册的回调函数
        foreach ($event->getCallbacks() as $callback) {
            try {
                $callback($event);
            } catch (Throwable $e) {
                $this->logger->error('Event callback execution failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                continue;
            }
        }
        // 清理
        $event->clearCallbacks();
    }
}

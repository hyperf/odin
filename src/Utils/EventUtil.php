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

namespace Hyperf\Odin\Utils;

use Hyperf\Context\ApplicationContext;
use Psr\EventDispatcher\EventDispatcherInterface;

class EventUtil
{
    public static function dispatch(object $event): void
    {
        $container = ApplicationContext::getContainer();
        if (! $container->has(EventDispatcherInterface::class)) {
            return;
        }
        $dispatcher = ApplicationContext::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->dispatch($event);
    }
}

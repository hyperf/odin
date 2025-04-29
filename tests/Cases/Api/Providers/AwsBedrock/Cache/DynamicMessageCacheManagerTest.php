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

namespace HyperfTest\Odin\Cases\Api\Providers\AwsBedrock\Cache;

use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\Strategy\DynamicMessageCacheManager;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class DynamicMessageCacheManagerTest extends AbstractTestCase
{
    public function testRestPointIndex()
    {
        $cachePointManager = new DynamicMessageCacheManager([]);
        $cachePointManager->addCachePointIndex(0);
        $cachePointManager->addCachePointIndex(3);
        $cachePointManager->addCachePointIndex(8);
        $cachePointManager->addCachePointIndex(9);
        $cachePointManager->addCachePointIndex(12);
        $cachePointManager->addCachePointIndex(19);
        $this->assertEquals([0, 3, 8, 9, 12, 19], $cachePointManager->getCachePointIndex());
        $cachePointManager->resetPointIndex(4);
        $this->assertEquals([0, 9, 12, 19], $cachePointManager->getCachePointIndex());
    }
}

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

namespace HyperfTest\Odin\Cases\Api\Providers\Gemini\Cache;

use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\NoneCacheStrategy;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\NoneCacheStrategy
 */
class NoneCacheStrategyTest extends AbstractTestCase
{
    public function testApplyReturnsNull()
    {
        $config = new GeminiCacheConfig();
        $strategy = new NoneCacheStrategy();
        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );

        $result = $strategy->apply($config, $request);
        $this->assertNull($result);
    }

    public function testCreateOrUpdateCacheDoesNothing()
    {
        $config = new GeminiCacheConfig();
        $strategy = new NoneCacheStrategy();
        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );

        // Should not throw any exception
        $strategy->createOrUpdateCache($config, $request);
        $this->assertTrue(true);
    }
}

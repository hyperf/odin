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

use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\CachePointMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\CachePointMessage
 */
class CachePointMessageTest extends AbstractTestCase
{
    public function testCreateWithMessage()
    {
        $message = new UserMessage('test message');
        $tokens = 100;
        $cachePointMessage = new CachePointMessage($message, $tokens);

        $this->assertEquals($message, $cachePointMessage->getOriginMessage());
        $this->assertEquals($tokens, $cachePointMessage->getTokens());
        $this->assertEquals($message->getHash(), $cachePointMessage->getHash());
    }

    public function testCreateWithArray()
    {
        $data = ['key' => 'value'];
        $tokens = 50;
        $cachePointMessage = new CachePointMessage($data, $tokens);

        $this->assertEquals($data, $cachePointMessage->getOriginMessage());
        $this->assertEquals($tokens, $cachePointMessage->getTokens());
        $this->assertEquals(md5(serialize($data)), $cachePointMessage->getHash());
    }

    public function testHashConsistency()
    {
        $message = new UserMessage('test message');
        $cachePointMessage1 = new CachePointMessage($message, 100);
        $cachePointMessage2 = new CachePointMessage($message, 200);

        // Hash should be the same regardless of tokens
        $this->assertEquals($cachePointMessage1->getHash(), $cachePointMessage2->getHash());
    }
}

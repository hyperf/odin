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
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\GeminiMessageCacheManager;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\GeminiMessageCacheManager
 */
class GeminiMessageCacheManagerTest extends AbstractTestCase
{
    public function testGetCacheKey()
    {
        $tools = ['tool1'];
        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $cachePointMessages = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);
        $cacheKey = $manager->getCacheKey('test-model');

        $this->assertStringStartsWith('gemini_cache:', $cacheKey);
        $this->assertEquals(45, strlen($cacheKey)); // 'gemini_cache:' (13 chars) + 32 char md5
    }

    public function testGetPrefixHash()
    {
        $tools = ['tool1'];
        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $cachePointMessages = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);
        $hash1 = $manager->getPrefixHash('test-model');
        $hash2 = $manager->getPrefixHash('test-model');

        // Hash should be consistent
        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(32, strlen($hash1));
    }

    public function testGetTokens()
    {
        $tools = ['tool1'];
        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $cachePointMessages = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);

        $this->assertEquals(100, $manager->getToolTokens());
        $this->assertEquals(50, $manager->getSystemTokens());
        $this->assertEquals(30, $manager->getFirstUserMessageTokens());
        $this->assertEquals(180, $manager->getPrefixTokens()); // 100 + 50 + 30
        $this->assertEquals(150, $manager->getBasePrefixTokens()); // 100 + 50
    }

    public function testGetTokensWithoutTools()
    {
        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $cachePointMessages = [
            0 => new CachePointMessage([], 0), // Empty tools
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);

        $this->assertEquals(0, $manager->getToolTokens());
        $this->assertEquals(50, $manager->getSystemTokens());
        $this->assertEquals(30, $manager->getFirstUserMessageTokens());
        $this->assertEquals(80, $manager->getPrefixTokens());
        $this->assertEquals(50, $manager->getBasePrefixTokens());
    }

    public function testCalculateTotalTokens()
    {
        $cachePointMessages = [
            0 => new CachePointMessage(['tools'], 100),
            1 => new CachePointMessage(new SystemMessage('system'), 50),
            2 => new CachePointMessage(new UserMessage('user1'), 30),
            3 => new CachePointMessage(new AssistantMessage('assistant1'), 40),
            4 => new CachePointMessage(new UserMessage('user2'), 25),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);

        // Calculate tokens from index 2 to 4
        $this->assertEquals(95, $manager->calculateTotalTokens(2, 4)); // 30 + 40 + 25

        // Calculate tokens from index 3 to 4
        $this->assertEquals(65, $manager->calculateTotalTokens(3, 4)); // 40 + 25

        // Invalid range
        $this->assertEquals(0, $manager->calculateTotalTokens(5, 4));
    }

    public function testGetLastMessageIndex()
    {
        $cachePointMessages = [
            0 => new CachePointMessage(['tools'], 100),
            1 => new CachePointMessage(new SystemMessage('system'), 50),
            2 => new CachePointMessage(new UserMessage('user1'), 30),
            3 => new CachePointMessage(new AssistantMessage('assistant1'), 40),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);
        $this->assertEquals(3, $manager->getLastMessageIndex());
    }

    public function testIsContinuousConversation()
    {
        $tools = ['tool1'];
        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $cachePointMessages1 = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $cachePointMessages2 = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];

        $manager1 = new GeminiMessageCacheManager($cachePointMessages1);
        $manager2 = new GeminiMessageCacheManager($cachePointMessages2);

        $this->assertTrue($manager1->isContinuousConversation($manager2, 'test-model'));

        // Different user message
        $cachePointMessages3 = [
            0 => new CachePointMessage($tools, 100),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage(new UserMessage('different message'), 30),
        ];
        $manager3 = new GeminiMessageCacheManager($cachePointMessages3);

        $this->assertFalse($manager1->isContinuousConversation($manager3, 'test-model'));
    }

    public function testGetFirstUserMessageIndex()
    {
        $cachePointMessages = [
            0 => new CachePointMessage(['tools'], 100),
            1 => new CachePointMessage(new SystemMessage('system'), 50),
            2 => new CachePointMessage(new UserMessage('user1'), 30),
            3 => new CachePointMessage(new AssistantMessage('assistant1'), 40),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);
        $this->assertEquals(2, $manager->getFirstUserMessageIndex());
    }

    public function testGetFirstUserMessageIndexWithoutUserMessage()
    {
        $cachePointMessages = [
            0 => new CachePointMessage(['tools'], 100),
            1 => new CachePointMessage(new SystemMessage('system'), 50),
        ];

        $manager = new GeminiMessageCacheManager($cachePointMessages);
        $this->assertNull($manager->getFirstUserMessageIndex());
    }
}

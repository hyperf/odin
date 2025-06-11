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

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AwsBedrockCachePointManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AwsBedrockCachePointManager
 */
class AwsBedrockCachePointManagerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        ClassLoader::init();
        ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
        parent::setUp();
    }

    /**
     * 测试当总Token数小于阈值时不设置缓存点.
     */
    public function testNoConfigureCachePointsWhenTokensBelow()
    {
        $autoCacheConfig = new AutoCacheConfig(4, 2048);

        $messages = [
            new UserMessage('这是一条短消息'),
        ];
        $chatRequest = new ChatCompletionRequest($messages, 'claude-3-sonnet');

        $cachePointManager = new AwsBedrockCachePointManager($autoCacheConfig);
        $cachePointManager->configureCachePoints($chatRequest);

        foreach ($chatRequest->getMessages() as $message) {
            $this->assertNull($message->getCachePoint());
        }
        $this->assertFalse($chatRequest->isToolsCache());
    }

    /**
     * 测试当总Token数大于阈值时设置缓存点.
     */
    public function testConfigureCachePointsWhenTokensAbove()
    {
        $this->markTestSkipped('此测试需要完整的缓存配置，跳过以避免依赖注入问题');
    }
}

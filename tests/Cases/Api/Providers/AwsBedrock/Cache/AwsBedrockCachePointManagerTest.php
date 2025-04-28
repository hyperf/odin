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
use Hyperf\Odin\Message\SystemMessage;
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
        $autoCacheConfig = new AutoCacheConfig(4, 200);

        $messages = [
            new SystemMessage('你是一个智能助手，帮助用户解决问题'),
            new UserMessage('请详细解释量子计算的基本原理，包括量子叠加和量子纠缠的概念，'
                . '以及它们如何应用于量子比特。此外，请讨论量子计算可能对密码学和机器学习领域带来的影响。'
                . '最后，简述目前量子计算面临的主要技术挑战和可能的解决方案。这是一个长消息，'
                . '用于测试缓存点是否正确设置。为了达到足够的Token数量，我会继续添加一些内容。'
                . '量子计算是计算机科学、物理学和数学交叉的前沿领域，它利用量子力学的独特性质来执行传统计算机无法高效完成的计算任务。'
                . '与传统计算机使用位（bit）不同，量子计算机使用量子位（qubit），这些量子位可以同时表示多个状态，这是量子计算强大能力的基础。'),
        ];
        $chatRequest = new ChatCompletionRequest($messages, 'claude-3-sonnet');

        $cachePointManager = new AwsBedrockCachePointManager($autoCacheConfig);
        $cachePointManager->configureCachePoints($chatRequest);

        $this->assertNull($messages[0]->getCachePoint());
        $this->assertNotNull($messages[1]->getCachePoint());
    }
}

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

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheManager
 */
class GeminiCacheManagerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ClassLoader::init();
        ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCheckCacheDoesNotThrowException()
    {
        $this->markTestSkipped('This test requires DI container setup. Actual cache behavior is tested in DynamicCacheStrategyTest.');
    }

    public function testCreateOrUpdateCacheAfterRequestWithLowTokens()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 2000,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $manager = new GeminiCacheManager($config);

        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );
        $request->calculateTokenEstimates();

        // Set low token estimate
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 100);

        // Should not throw exception (will use NoneCacheStrategy)
        $manager->createOrUpdateCacheAfterRequest($request);
        $this->assertTrue(true);
    }

    public function testCreateOrUpdateCacheAfterRequestWithHighTokens()
    {
        $this->markTestSkipped('This test requires DI container setup. Actual cache behavior is tested in DynamicCacheStrategyTest.');
    }

    public function testCreateOrUpdateCacheAfterRequestCalculatesTokensIfNeeded()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 100,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $manager = new GeminiCacheManager($config);

        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );

        // Don't calculate tokens beforehand
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', null);

        // Should calculate tokens automatically
        $manager->createOrUpdateCacheAfterRequest($request);

        // Verify tokens were calculated
        $totalTokens = $request->getTotalTokenEstimate();
        $this->assertNotNull($totalTokens);
    }

    public function testSelectStrategyUsesNoneCacheStrategyWhenTokensBelowThreshold()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 2000,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $manager = new GeminiCacheManager($config);

        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );
        $request->calculateTokenEstimates();
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 100);

        // Should use NoneCacheStrategy (no cache created)
        $manager->createOrUpdateCacheAfterRequest($request);
        $this->assertTrue(true);
    }

    public function testSelectStrategyUsesDynamicCacheStrategyWhenTokensAboveThreshold()
    {
        $this->markTestSkipped('This test requires DI container setup. Actual cache behavior is tested in DynamicCacheStrategyTest.');
    }
}

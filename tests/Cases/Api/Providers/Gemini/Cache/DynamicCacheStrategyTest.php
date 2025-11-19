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

use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheClient;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\CachePointMessage;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\DynamicCacheStrategy;
use Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\GeminiMessageCacheManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use HyperfTest\Odin\Mock\Cache;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy\DynamicCacheStrategy
 */
class DynamicCacheStrategyTest extends AbstractTestCase
{
    private CacheInterface $cache;

    /** @var GeminiCacheClient&MockInterface */
    private GeminiCacheClient $cacheClient;

    /** @var null|LoggerInterface&MockInterface */
    private ?LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        ClassLoader::init();
        ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

        $this->cache = new Cache();
        $this->cacheClient = Mockery::mock(GeminiCacheClient::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
    }

    protected function tearDown(): void
    {
        // Clear cache between tests
        $this->cache->clear();
        Mockery::close();
        parent::tearDown();
    }

    public function testApplyReturnsNullWhenNoMessages()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);
        $request = new ChatCompletionRequest([], 'test-model');

        $result = $strategy->apply($config, $request);
        $this->assertNull($result);
    }

    public function testApplyReturnsNullWhenNoCachedData()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);
        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );

        // Cache is empty, so get will return null
        $result = $strategy->apply($config, $request);
        $this->assertNull($result);
    }

    public function testApplyReturnsNullWhenNoLastMessageCacheManager()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);
        $request = new ChatCompletionRequest(
            [new UserMessage('test')],
            'test-model'
        );

        // Set empty cache data
        $cacheKey = 'gemini_cache:' . md5('test-model' . '' . '' . '');
        $this->cache->set($cacheKey, []);

        $result = $strategy->apply($config, $request);
        $this->assertNull($result);
    }

    public function testApplyReturnsCacheInfoWhenContinuousConversation()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'test-model'
        );

        // Create message cache manager for cached data
        $cachedCachePointMessages = [
            0 => new CachePointMessage([], 0),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage, 30),
        ];
        $lastMessageCacheManager = new GeminiMessageCacheManager($cachedCachePointMessages);

        $cacheName = 'cachedContents/test-cache-123';
        $cachedData = [
            'message_cache_manager' => $lastMessageCacheManager,
            'cache_name' => $cacheName,
            'cached_message_count' => 0,
        ];

        // Set cache data
        $cacheKey = $lastMessageCacheManager->getCacheKey('test-model');
        $this->cache->set($cacheKey, $cachedData);

        $result = $strategy->apply($config, $request);

        $this->assertNotNull($result);
        $this->assertEquals($cacheName, $result['cache_name']);
        $this->assertTrue($result['has_system']);
        $this->assertFalse($result['has_tools']);
        $this->assertFalse($result['has_first_user_message']); // cached_message_count is 0
    }

    public function testApplyReturnsNullWhenNotContinuousConversation()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'test-model'
        );

        // Create message cache manager with different user message
        $cachedCachePointMessages = [
            0 => new CachePointMessage([], 0),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage(new UserMessage('different message'), 30),
        ];
        $lastMessageCacheManager = new GeminiMessageCacheManager($cachedCachePointMessages);

        $cachedData = [
            'message_cache_manager' => $lastMessageCacheManager,
            'cache_name' => 'cachedContents/test-cache-123',
            'cached_message_count' => 0,
        ];

        // Set cache data
        $cacheKey = $lastMessageCacheManager->getCacheKey('test-model');
        $this->cache->set($cacheKey, $cachedData);

        $result = $strategy->apply($config, $request);
        $this->assertNull($result);
    }

    public function testCreateOrUpdateCacheDoesNothingWhenNoMessages()
    {
        $config = new GeminiCacheConfig();
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);
        $request = new ChatCompletionRequest([], 'test-model');

        $strategy->createOrUpdateCache($config, $request);
        $this->assertTrue(true);
    }

    public function testCreateOrUpdateCacheCreatesCacheWhenBasePrefixTokensAboveThreshold()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 100,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system instruction');
        $userMessage = new UserMessage('user message');

        // Use a model with lower threshold for testing
        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'gemini-2.5-flash' // This model has minCacheTokens = 1024
        );
        $request->calculateTokenEstimates();

        // Set token estimates to meet threshold
        // basePrefixTokens = systemTokens (1500) + toolsTokens (0) = 1500
        // minCacheTokens = max(1024, 100) = 1024
        // 1500 >= 1024, so cache should be created
        $this->setNonpublicPropertyValue($systemMessage, 'tokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request, 'systemTokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 2000);

        // Cache is empty initially
        $this->cacheClient->shouldReceive('createCache')
            ->once()
            ->andReturn('cachedContents/new-cache-123');

        $this->logger->shouldReceive('warning')->never();

        $strategy->createOrUpdateCache($config, $request);

        // Verify cache was created and stored
        $messageCacheManager = $this->callNonpublicMethod($strategy, 'createMessageCacheManager', $request);
        $cacheKey = $messageCacheManager->getCacheKey('gemini-2.5-flash');
        $cachedData = $this->cache->get($cacheKey);
        $this->assertNotNull($cachedData);
        $this->assertEquals('cachedContents/new-cache-123', $cachedData['cache_name']);
        $this->assertEquals(0, $cachedData['cached_message_count']);
    }

    public function testCreateOrUpdateCacheDoesNotCreateWhenBasePrefixTokensBelowThreshold()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 200,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system');
        $userMessage = new UserMessage('user message');

        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'test-model'
        );
        $request->calculateTokenEstimates();

        // Set token estimates below threshold
        // Note: getMinCacheTokensByModel('test-model') returns 4096 (default)
        // So we need to ensure basePrefixTokens < max(4096, 200) = 4096
        $this->setNonpublicPropertyValue($systemMessage, 'tokenEstimate', 50);
        $this->setNonpublicPropertyValue($request, 'systemTokenEstimate', 50);
        $this->setNonpublicPropertyValue($request, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 100);

        // Cache is empty initially
        $this->cacheClient->shouldReceive('createCache')->never();

        $strategy->createOrUpdateCache($config, $request);
        
        // Verify no cache was created
        $messageCacheManager = $this->callNonpublicMethod($strategy, 'createMessageCacheManager', $request);
        $cacheKey = $messageCacheManager->getCacheKey('test-model');
        $cachedData = $this->cache->get($cacheKey);
        $this->assertNull($cachedData);
    }

    public function testCreateOrUpdateCacheMovesCachePointWhenIncrementalTokensAboveThreshold()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 100,
            refreshPointMinTokens: 50, // Lower threshold for testing
            ttl: 600,
            enableAutoCache: true
        );
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system');
        $userMessage1 = new UserMessage('user message 1');
        $assistantMessage = new AssistantMessage('assistant message');
        $userMessage2 = new UserMessage('user message 2');

        // Use a model with lower threshold for testing
        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage1, $assistantMessage, $userMessage2],
            'gemini-2.5-flash'
        );
        $request->calculateTokenEstimates();

        // Set token estimates
        // basePrefixTokens = systemTokens (1500) + toolsTokens (0) = 1500 >= 1024 (minCacheTokens for flash)
        // incrementalTokens = assistantMessage (40) + userMessage2 (35) = 75 >= 50 (refreshPointMinTokens)
        $this->setNonpublicPropertyValue($systemMessage, 'tokenEstimate', 1500);
        $this->setNonpublicPropertyValue($userMessage1, 'tokenEstimate', 30);
        $this->setNonpublicPropertyValue($assistantMessage, 'tokenEstimate', 40);
        $this->setNonpublicPropertyValue($userMessage2, 'tokenEstimate', 35);
        $this->setNonpublicPropertyValue($request, 'systemTokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 1605);

        // Create cached data with continuous conversation
        $cachedCachePointMessages = [
            0 => new CachePointMessage([], 0),
            1 => new CachePointMessage($systemMessage, 50),
            2 => new CachePointMessage($userMessage1, 30),
        ];
        $lastMessageCacheManager = new GeminiMessageCacheManager($cachedCachePointMessages);

        $oldCacheName = 'cachedContents/old-cache-123';
        $cachedData = [
            'message_cache_manager' => $lastMessageCacheManager,
            'cache_name' => $oldCacheName,
            'cached_message_count' => 0,
        ];

        // Set cached data
        $cacheKey = $lastMessageCacheManager->getCacheKey('gemini-2.5-flash');
        $this->cache->set($cacheKey, $cachedData);

        $this->cacheClient->shouldReceive('deleteCache')
            ->once()
            ->with($oldCacheName)
            ->andReturn(null);

        $newCacheName = 'cachedContents/new-cache-456';
        $this->cacheClient->shouldReceive('createCache')
            ->once()
            ->andReturn($newCacheName);

        $this->logger->shouldReceive('warning')->never();

        $strategy->createOrUpdateCache($config, $request);

        // Verify cache point was moved
        $newCachedData = $this->cache->get($cacheKey);
        $this->assertNotNull($newCachedData);
        $this->assertEquals($newCacheName, $newCachedData['cache_name']);
        $this->assertGreaterThan(0, $newCachedData['cached_message_count']);
    }

    public function testCreateOrUpdateCacheHandlesExceptionGracefully()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 100,
            refreshPointMinTokens: 5000,
            ttl: 600,
            enableAutoCache: true
        );
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system instruction');
        $userMessage = new UserMessage('user message');

        // Use a model with lower threshold for testing
        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'gemini-2.5-flash'
        );
        $request->calculateTokenEstimates();

        $this->setNonpublicPropertyValue($systemMessage, 'tokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request, 'systemTokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request, 'totalTokenEstimate', 2000);

        // Cache is empty initially
        $this->cacheClient->shouldReceive('createCache')
            ->once()
            ->andThrow(new Exception('API error'));

        $this->logger->shouldReceive('warning')
            ->once()
            ->with(
                'Failed to create Gemini cache after request',
                Mockery::on(function ($context) {
                    return isset($context['error']) && isset($context['model']);
                })
            );

        // Should not throw exception
        $strategy->createOrUpdateCache($config, $request);

        // Verify exception was handled gracefully - no cache was created
        $messageCacheManager = $this->callNonpublicMethod($strategy, 'createMessageCacheManager', $request);
        $cacheKey = $messageCacheManager->getCacheKey('gemini-2.5-flash');
        $cachedData = $this->cache->get($cacheKey);
        $this->assertNull($cachedData);
    }

    /**
     * Test complete cache lifecycle: create -> hit -> update -> hit after update.
     */
    public function testCompleteCacheLifecycle()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 100,
            refreshPointMinTokens: 50, // Lower threshold for testing
            ttl: 600,
            enableAutoCache: true
        );
        $strategy = new DynamicCacheStrategy($this->cache, $this->cacheClient, $this->logger);

        $systemMessage = new SystemMessage('system instruction');
        $userMessage1 = new UserMessage('user message 1');

        // Step 1: First request - Create cache
        $request1 = new ChatCompletionRequest(
            [$systemMessage, $userMessage1],
            'gemini-2.5-flash'
        );
        $request1->calculateTokenEstimates();

        $this->setNonpublicPropertyValue($systemMessage, 'tokenEstimate', 1500);
        $this->setNonpublicPropertyValue($userMessage1, 'tokenEstimate', 30);
        $this->setNonpublicPropertyValue($request1, 'systemTokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request1, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request1, 'totalTokenEstimate', 1530);

        $cacheName1 = 'cachedContents/cache-1';
        $this->cacheClient->shouldReceive('createCache')
            ->once()
            ->andReturn($cacheName1);

        $strategy->createOrUpdateCache($config, $request1);

        // Verify cache was created
        $messageCacheManager1 = $this->callNonpublicMethod($strategy, 'createMessageCacheManager', $request1);
        $cacheKey = $messageCacheManager1->getCacheKey('gemini-2.5-flash');
        $cachedData1 = $this->cache->get($cacheKey);
        $this->assertNotNull($cachedData1);
        $this->assertEquals($cacheName1, $cachedData1['cache_name']);
        $this->assertEquals(0, $cachedData1['cached_message_count']);

        // Step 2: Second request - Hit cache (apply)
        $request2 = new ChatCompletionRequest(
            [$systemMessage, $userMessage1],
            'gemini-2.5-flash'
        );

        $result2 = $strategy->apply($config, $request2);
        $this->assertNotNull($result2);
        $this->assertEquals($cacheName1, $result2['cache_name']);
        $this->assertTrue($result2['has_system']);
        $this->assertFalse($result2['has_first_user_message']); // cached_message_count is 0

        // Step 3: Third request with new message - Update cache (move cache point)
        $assistantMessage = new AssistantMessage('assistant response');
        $userMessage2 = new UserMessage('user message 2');

        $request3 = new ChatCompletionRequest(
            [$systemMessage, $userMessage1, $assistantMessage, $userMessage2],
            'gemini-2.5-flash'
        );
        $request3->calculateTokenEstimates();

        $this->setNonpublicPropertyValue($assistantMessage, 'tokenEstimate', 40);
        $this->setNonpublicPropertyValue($userMessage2, 'tokenEstimate', 35);
        $this->setNonpublicPropertyValue($request3, 'systemTokenEstimate', 1500);
        $this->setNonpublicPropertyValue($request3, 'toolsTokenEstimate', 0);
        $this->setNonpublicPropertyValue($request3, 'totalTokenEstimate', 1605);

        $cacheName2 = 'cachedContents/cache-2';
        $this->cacheClient->shouldReceive('deleteCache')
            ->once()
            ->with($cacheName1)
            ->andReturn(null);
        $this->cacheClient->shouldReceive('createCache')
            ->once()
            ->andReturn($cacheName2);

        $strategy->createOrUpdateCache($config, $request3);

        // Verify cache was updated
        $cachedData3 = $this->cache->get($cacheKey);
        $this->assertNotNull($cachedData3);
        $this->assertEquals($cacheName2, $cachedData3['cache_name']);
        $this->assertGreaterThan(0, $cachedData3['cached_message_count']);

        // Step 4: Fourth request - Hit cache after update (apply)
        $request4 = new ChatCompletionRequest(
            [$systemMessage, $userMessage1, $assistantMessage, $userMessage2],
            'gemini-2.5-flash'
        );

        $result4 = $strategy->apply($config, $request4);
        $this->assertNotNull($result4);
        $this->assertEquals($cacheName2, $result4['cache_name']);
        $this->assertTrue($result4['has_system']);
        // After update, cached_message_count > 0, so has_first_user_message should be true
        $this->assertTrue($result4['has_first_user_message']);
    }
}

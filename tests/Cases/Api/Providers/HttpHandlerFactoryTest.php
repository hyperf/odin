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

namespace HyperfTest\Odin\Cases\Api\Providers;

use GuzzleHttp\Handler\StreamHandler;
use Hyperf\Odin\Api\Providers\HttpHandlerFactory;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\HttpHandlerFactory
 */
class HttpHandlerFactoryTest extends AbstractTestCase
{
    /**
     * 测试在非协程上下文中的 auto 模式.
     */
    public function testCreateAutoHandlerInNonCoroutineContext()
    {
        // 在非协程上下文中，应该使用默认的 auto 处理器
        $handler = HttpHandlerFactory::create('auto');
        $this->assertIsCallable($handler);
    }

    /**
     * 测试显式创建 stream 处理器.
     */
    public function testCreateStreamHandler()
    {
        $handler = HttpHandlerFactory::createStreamHandler();
        $this->assertInstanceOf(StreamHandler::class, $handler);
    }

    /**
     * 测试显式创建 curl 处理器.
     */
    public function testCreateCurlHandler()
    {
        $handler = HttpHandlerFactory::createCurlHandler();
        $this->assertIsCallable($handler);
    }

    /**
     * 测试协程检测方法在非协程上下文中返回 false.
     */
    public function testIsInCoroutineContextReturnsFalseInNonCoroutineContext()
    {
        // 在测试环境中（非协程上下文），应该返回 false
        $result = HttpHandlerFactory::isInCoroutineContext();
        $this->assertFalse($result);
    }

    /**
     * 测试环境信息获取.
     */
    public function testGetEnvironmentInfo()
    {
        $info = HttpHandlerFactory::getEnvironmentInfo();
        
        $this->assertIsArray($info);
        $this->assertArrayHasKey('curl_available', $info);
        $this->assertArrayHasKey('curl_multi_available', $info);
        $this->assertArrayHasKey('stream_available', $info);
        $this->assertArrayHasKey('openssl_available', $info);
        $this->assertArrayHasKey('in_coroutine_context', $info);
        $this->assertArrayHasKey('recommended_handler', $info);
        
        $this->assertIsBool($info['curl_available']);
        $this->assertIsBool($info['curl_multi_available']);
        $this->assertIsBool($info['stream_available']);
        $this->assertIsBool($info['openssl_available']);
        $this->assertIsBool($info['in_coroutine_context']);
        $this->assertIsString($info['recommended_handler']);
    }

    /**
     * 测试在非协程上下文中的推荐处理器.
     */
    public function testGetRecommendedHandlerInNonCoroutineContext()
    {
        $recommended = HttpHandlerFactory::getRecommendedHandler();
        
        // 在非协程上下文中，应该根据 curl 和 stream 的可用性返回推荐的处理器
        $this->assertIsString($recommended);
        $this->assertContains($recommended, ['curl', 'stream', 'auto']);
    }

    /**
     * 测试 Guzzle 客户端创建.
     */
    public function testCreateGuzzleClient()
    {
        $client = HttpHandlerFactory::createGuzzleClient([], 'stream');
        $this->assertInstanceOf(\GuzzleHttp\Client::class, $client);
    }

    /**
     * 测试处理器可用性检查.
     */
    public function testIsHandlerAvailable()
    {
        $this->assertTrue(HttpHandlerFactory::isHandlerAvailable('auto'));
        
        // stream 处理器应该在大多数环境中可用
        $this->assertIsBool(HttpHandlerFactory::isHandlerAvailable('stream'));
        
        // curl 处理器的可用性取决于环境
        $this->assertIsBool(HttpHandlerFactory::isHandlerAvailable('curl'));
    }

    /**
     * 测试 HTTP 选项创建.
     */
    public function testCreateHttpOptions()
    {
        $baseOptions = ['timeout' => 30];
        $options = HttpHandlerFactory::createHttpOptions($baseOptions, 'stream');
        
        $this->assertIsArray($options);
        $this->assertArrayHasKey('timeout', $options);
        $this->assertEquals(30, $options['timeout']);
        $this->assertArrayHasKey('handler', $options);
    }
}

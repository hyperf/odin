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

namespace HyperfTest\Odin\Cases\Api\Transport;

use Hyperf\Odin\Api\Transport\StreamExceptionDetector;
use Hyperf\Odin\Exception\LLMException\Network\LLMStreamTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMThinkingStreamTimeoutException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Transport\StreamExceptionDetector
 */
class StreamExceptionDetectorTest extends AbstractTestCase
{
    /**
     * 清理测试资源.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试默认配置.
     */
    public function testDefaultConfig()
    {
        $detector = new StreamExceptionDetector([]);

        // 使用反射检查内部配置
        $config = $this->getNonpublicProperty($detector, 'timeoutConfig');

        $this->assertEquals(600.0, $config['total']); // 流式处理默认超时更长
        $this->assertEquals(60.0, $config['stream_first']);
        $this->assertEquals(30.0, $config['stream_chunk']);
    }

    /**
     * 测试自定义配置.
     */
    public function testCustomConfig()
    {
        $customConfig = [
            'total' => 60.0,
            'stream_first' => 10.0,
            'stream_chunk' => 5.0,
        ];

        $detector = new StreamExceptionDetector($customConfig);

        // 使用反射检查内部配置
        $config = $this->getNonpublicProperty($detector, 'timeoutConfig');

        $this->assertEquals(60.0, $config['total']);
        $this->assertEquals(10.0, $config['stream_first']);
        $this->assertEquals(5.0, $config['stream_chunk']);
    }

    /**
     * 测试总超时检测.
     */
    public function testTotalTimeout()
    {
        $config = [
            'total' => 1.0, // 1秒
        ];

        $detector = new StreamExceptionDetector($config);

        // 设置开始时间为超过1秒前
        $this->setNonpublicPropertyValue($detector, 'startTime', microtime(true) - 2);

        $this->expectException(LLMStreamTimeoutException::class);
        $this->expectExceptionMessage('流式响应总体超时');

        $detector->checkTimeout();
    }

    /**
     * 测试首个块超时检测.
     */
    public function testFirstChunkTimeout()
    {
        $config = [
            'total' => 10.0,
            'stream_first' => 1.0, // 1秒
        ];

        $detector = new StreamExceptionDetector($config);

        // 设置开始时间为超过1秒前，但不设置首个块接收标志
        $this->setNonpublicPropertyValue($detector, 'startTime', microtime(true) - 2);
        $this->setNonpublicPropertyValue($detector, 'firstChunkReceived', false);

        $this->expectException(LLMThinkingStreamTimeoutException::class);
        $this->expectExceptionMessage('等待首个流式响应块超时');

        $detector->checkTimeout();
    }

    /**
     * 测试块间隔超时检测.
     */
    public function testChunkIntervalTimeout()
    {
        $config = [
            'total' => 10.0,
            'stream_first' => 1.0,
            'stream_chunk' => 1.0, // 1秒
        ];

        $detector = new StreamExceptionDetector($config);

        // 设置已收到首个块
        $this->setNonpublicPropertyValue($detector, 'firstChunkReceived', true);
        // 设置上次收到块的时间为超过1秒前
        $this->setNonpublicPropertyValue($detector, 'lastChunkTime', microtime(true) - 2);

        $this->expectException(LLMStreamTimeoutException::class);
        $this->expectExceptionMessage('流式响应块间超时');

        $detector->checkTimeout();
    }

    /**
     * 测试块接收处理.
     */
    public function testOnChunkReceived()
    {
        /** @var LoggerInterface|MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        // @phpstan-ignore-next-line
        $logger->shouldReceive('debug')->once()->with(
            'First chunk received',
            Mockery::on(function ($context) {
                return isset($context['initial_response_time']);
            })
        );

        $detector = new StreamExceptionDetector([], $logger);

        // 设置开始时间
        $startTime = microtime(true) - 1;
        $this->setNonpublicPropertyValue($detector, 'startTime', $startTime);

        // 模拟接收首个块
        $detector->onChunkReceived();

        // 验证标志位和时间戳
        $this->assertTrue($this->getNonpublicProperty($detector, 'firstChunkReceived'));
        $this->assertGreaterThan($startTime, $this->getNonpublicProperty($detector, 'lastChunkTime'));

        // 模拟接收第二个块
        $lastTime = $this->getNonpublicProperty($detector, 'lastChunkTime');
        usleep(10000); // 10毫秒
        $detector->onChunkReceived();

        // 验证时间戳已更新
        $this->assertGreaterThan($lastTime, $this->getNonpublicProperty($detector, 'lastChunkTime'));
    }

    /**
     * 测试未超时情况下的检查.
     */
    public function testNoTimeout()
    {
        $detector = new StreamExceptionDetector([
            'total' => 10.0,
            'stream_first' => 5.0,
            'stream_chunk' => 3.0,
        ]);

        // 设置所有时间为当前时间，不会触发超时
        $now = microtime(true);
        $this->setNonpublicPropertyValue($detector, 'startTime', $now);
        $this->setNonpublicPropertyValue($detector, 'lastChunkTime', $now);

        // 这两种情况都不应触发异常

        // 1. 未收到首个块，但未超时
        $this->setNonpublicPropertyValue($detector, 'firstChunkReceived', false);
        $detector->checkTimeout();

        // 2. 已收到首个块，但未超时
        $this->setNonpublicPropertyValue($detector, 'firstChunkReceived', true);
        $detector->checkTimeout();

        // 验证执行到这里没有异常抛出
        $this->assertTrue(true);
    }
}

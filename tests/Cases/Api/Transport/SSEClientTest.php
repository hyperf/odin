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

use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Api\Transport\SSEEvent;
use Hyperf\Odin\Exception\InvalidArgumentException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Transport\SSEClient
 */
class SSEClientTest extends AbstractTestCase
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
     * 测试从流式输入创建事件.
     */
    public function testCreateFromStream()
    {
        // 创建模拟的内存流
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "data: {\"message\":\"Hello World\"}\n\n");
        rewind($stream);

        // 创建SSEClient实例
        $sseClient = new SSEClient($stream);

        // 获取迭代器并测试事件
        $events = iterator_to_array($sseClient->getIterator());

        $this->assertCount(1, $events);
        $this->assertInstanceOf(SSEEvent::class, $events[0]);
        $this->assertEquals(['message' => 'Hello World'], $events[0]->getData());
        $this->assertEquals('message', $events[0]->getEvent());
    }

    /**
     * 测试创建SSEClient时传入无效流时抛出异常.
     */
    public function testInvalidStreamThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream must be a resource');

        // 传入非资源类型
        // @phpstan-ignore-next-line
        new SSEClient('not a stream');
    }

    /**
     * 测试事件解析.
     */
    public function testEventParsing()
    {
        // 创建包含多个字段的SSE数据
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "event: custom-event\ndata: {\"key\":\"value\"}\nid: 123\nretry: 5000\n\n");
        rewind($stream);

        $sseClient = new SSEClient($stream);
        $events = iterator_to_array($sseClient->getIterator());

        $this->assertCount(1, $events);
        $event = $events[0];

        // 验证事件字段
        $this->assertEquals('custom-event', $event->getEvent());
        $this->assertEquals(['key' => 'value'], $event->getData());
        $this->assertEquals('123', $event->getId());
        $this->assertEquals(5000, $event->getRetry());

        // 验证lastEventId被设置
        $this->assertEquals('123', $sseClient->getLastEventId());

        // 验证重试超时被设置
        $this->assertEquals(5000, $sseClient->getRetryTimeout());
    }

    /**
     * 测试多行数据解析.
     */
    public function testMultilineDataParsing()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "data: line1\ndata: line2\ndata: line3\n\n");
        rewind($stream);

        $sseClient = new SSEClient($stream);
        $events = iterator_to_array($sseClient->getIterator());

        $this->assertCount(1, $events);
        // 多行数据会被合并并包含换行符
        $this->assertEquals("line1\nline2\nline3", $events[0]->getData());
    }

    /**
     * 测试无效JSON处理.
     */
    public function testInvalidJsonHandling()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "data: {invalid json}\n\n");
        rewind($stream);

        $sseClient = new SSEClient($stream);
        $events = iterator_to_array($sseClient->getIterator());

        $this->assertCount(1, $events);
        // 无效JSON仍然保持为原始字符串
        $this->assertEquals('{invalid json}', $events[0]->getData());
    }

    /**
     * 测试超时检测功能.
     * SSEClient 通过 StreamExceptionDetector 来处理超时检测，而不是直接提供 isTimedOut 方法.
     */
    public function testIsTimedOut()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "data: test\n\n");
        rewind($stream);

        // 创建SSEClient实例，通过timeoutConfig传递1秒超时
        $sseClient = new SSEClient($stream, true, ['stream_total' => 1]);

        // 验证 StreamExceptionDetector 已创建
        $exceptionDetector = $this->getNonpublicProperty($sseClient, 'exceptionDetector');
        $this->assertNotNull($exceptionDetector);

        // 验证超时配置已正确设置
        $timeoutConfig = $this->getNonpublicProperty($exceptionDetector, 'timeoutConfig');
        $this->assertEquals(1.0, $timeoutConfig['total']);
    }

    /**
     * 测试处理空事件.
     */
    public function testEmptyEventHandling()
    {
        $stream = fopen('php://memory', 'r+');
        // 写入注释和空行，这些应该被忽略
        fwrite($stream, ": this is a comment\n\n");
        fwrite($stream, "\n\n");
        // 写入有效事件
        fwrite($stream, "data: valid\n\n");
        rewind($stream);

        $sseClient = new SSEClient($stream);
        $events = iterator_to_array($sseClient->getIterator());

        // 只有一个有效事件被处理
        $this->assertCount(1, $events);
        $this->assertEquals('valid', $events[0]->getData());
    }
}

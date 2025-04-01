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

use Hyperf\Odin\Api\Transport\SSEEvent;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Transport\SSEEvent
 */
class SSEEventTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        $event = new SSEEvent('test data', 'custom-event', '123', 5000);

        $this->assertEquals('test data', $event->getData());
        $this->assertEquals('custom-event', $event->getEvent());
        $this->assertEquals('123', $event->getId());
        $this->assertEquals(5000, $event->getRetry());
    }

    /**
     * 测试默认值.
     */
    public function testDefaultValues()
    {
        $event = new SSEEvent('test data');

        $this->assertEquals('test data', $event->getData());
        $this->assertEquals('message', $event->getEvent()); // 默认事件类型
        $this->assertNull($event->getId());
        $this->assertNull($event->getRetry());
    }

    /**
     * 测试从数组创建事件.
     */
    public function testFromArray()
    {
        $data = [
            'data' => 'test data',
            'event' => 'custom-event',
            'id' => '123',
            'retry' => 5000,
        ];

        $event = SSEEvent::fromArray($data);

        $this->assertEquals('test data', $event->getData());
        $this->assertEquals('custom-event', $event->getEvent());
        $this->assertEquals('123', $event->getId());
        $this->assertEquals(5000, $event->getRetry());
    }

    /**
     * 测试从不完整数组创建事件.
     */
    public function testFromIncompleteArray()
    {
        $data = [
            'data' => 'test data',
        ];

        $event = SSEEvent::fromArray($data);

        $this->assertEquals('test data', $event->getData());
        $this->assertEquals('message', $event->getEvent()); // 默认事件类型
        $this->assertNull($event->getId());
        $this->assertNull($event->getRetry());
    }

    /**
     * 测试转换为数组.
     */
    public function testToArray()
    {
        $event = new SSEEvent('test data', 'custom-event', '123', 5000);
        $array = $event->toArray();

        $this->assertEquals([
            'event' => 'custom-event',
            'data' => 'test data',
            'id' => '123',
            'retry' => 5000,
        ], $array);
    }

    /**
     * 测试JSON序列化.
     */
    public function testJsonSerialize()
    {
        $event = new SSEEvent('test data', 'custom-event', '123', 5000);
        $json = json_encode($event);

        $this->assertJsonStringEqualsJsonString(
            '{"event":"custom-event","data":"test data","id":"123","retry":5000}',
            $json
        );
    }

    /**
     * 测试事件格式化.
     */
    public function testFormat()
    {
        $event = new SSEEvent('test data', 'custom-event', '123', 5000);
        $formatted = $event->format();

        $expected = "event: custom-event\ndata: test data\nid: 123\nretry: 5000\n\n";
        $this->assertEquals($expected, $formatted);
    }

    /**
     * 测试默认事件类型格式化（不包含event行）.
     */
    public function testFormatDefaultEvent()
    {
        $event = new SSEEvent('test data', 'message', '123', 5000);
        $formatted = $event->format();

        // 当事件类型为默认的'message'，不应该包括event行
        $expected = "data: test data\nid: 123\nretry: 5000\n\n";
        $this->assertEquals($expected, $formatted);
    }

    /**
     * 测试多行数据格式化.
     */
    public function testFormatMultilineData()
    {
        $event = new SSEEvent("line1\nline2\nline3");
        $formatted = $event->format();

        $expected = "data: line1\ndata: line2\ndata: line3\n\n";
        $this->assertEquals($expected, $formatted);
    }

    /**
     * 测试数组数据格式化为JSON.
     */
    public function testFormatArrayData()
    {
        $data = ['key' => 'value', 'nested' => ['foo' => 'bar']];
        $event = new SSEEvent($data);
        $formatted = $event->format();

        $expected = "data: {\"key\":\"value\",\"nested\":{\"foo\":\"bar\"}}\n\n";
        $this->assertEquals($expected, $formatted);
    }

    /**
     * 测试空事件检查.
     */
    public function testIsEmpty()
    {
        $emptyEvent = new SSEEvent('');
        $this->assertTrue($emptyEvent->isEmpty());

        $nonEmptyEvent = new SSEEvent('data');
        $this->assertFalse($nonEmptyEvent->isEmpty());

        $emptyArrayEvent = new SSEEvent([]);
        $this->assertTrue($emptyArrayEvent->isEmpty());
    }

    /**
     * 测试setter方法.
     */
    public function testSetters()
    {
        $event = new SSEEvent();

        $event->setData('updated data');
        $this->assertEquals('updated data', $event->getData());

        $event->setEvent('updated-event');
        $this->assertEquals('updated-event', $event->getEvent());

        $event->setId('456');
        $this->assertEquals('456', $event->getId());

        $event->setRetry(3000);
        $this->assertEquals(3000, $event->getRetry());
    }
}

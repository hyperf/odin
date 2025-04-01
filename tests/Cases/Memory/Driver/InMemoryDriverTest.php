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

namespace HyperfTest\Odin\Cases\Memory\Driver;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Memory\Driver\InMemoryDriver;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 内存驱动测试.
 * @internal
 */
#[CoversClass(InMemoryDriver::class)]
class InMemoryDriverTest extends AbstractTestCase
{
    /**
     * 测试驱动是否正确实现接口.
     */
    public function testImplementsInterface()
    {
        $driver = new InMemoryDriver();
        $this->assertInstanceOf(DriverInterface::class, $driver);
    }

    /**
     * 测试添加和获取普通消息.
     */
    public function testAddAndGetMessages()
    {
        $driver = new InMemoryDriver();

        $message1 = new UserMessage('消息1');
        $message2 = new UserMessage('消息2');

        $driver->addMessage($message1);
        $driver->addMessage($message2);

        $messages = $driver->getMessages();

        $this->assertCount(2, $messages);
        $this->assertSame($message1, $messages[0]);
        $this->assertSame($message2, $messages[1]);
    }

    /**
     * 测试添加和获取系统消息.
     */
    public function testAddAndGetSystemMessages()
    {
        $driver = new InMemoryDriver();

        $message1 = new SystemMessage('系统消息1');
        $message2 = new SystemMessage('系统消息2');

        $driver->addSystemMessage($message1);
        $driver->addSystemMessage($message2);

        $messages = $driver->getSystemMessages();

        $this->assertCount(2, $messages);
        $this->assertSame($message1, $messages[0]);
        $this->assertSame($message2, $messages[1]);
    }

    /**
     * 测试清空消息.
     */
    public function testClear()
    {
        $driver = new InMemoryDriver();

        $driver->addMessage(new UserMessage('消息1'));
        $driver->addSystemMessage(new SystemMessage('系统消息1'));

        $this->assertCount(1, $driver->getMessages());
        $this->assertCount(1, $driver->getSystemMessages());

        $driver->clear();

        $this->assertCount(0, $driver->getMessages());
        $this->assertCount(0, $driver->getSystemMessages());
    }

    /**
     * 测试消息数量限制.
     */
    public function testMessageLimit()
    {
        $driver = new InMemoryDriver(['max_messages' => 2]);

        $message1 = new UserMessage('消息1');
        $message2 = new UserMessage('消息2');
        $message3 = new UserMessage('消息3');

        $driver->addMessage($message1);
        $driver->addMessage($message2);

        // 此时应该有两条消息
        $this->assertCount(2, $driver->getMessages());

        // 添加第三条消息，应该自动移除第一条
        $driver->addMessage($message3);

        $messages = $driver->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame($message2, $messages[0]);
        $this->assertSame($message3, $messages[1]);
    }

    /**
     * 测试配置参数功能.
     */
    public function testConfig()
    {
        $driver = new InMemoryDriver(['max_messages' => 50, 'custom_option' => 'value']);

        // 测试获取配置
        $this->assertSame(50, $driver->getConfig('max_messages'));
        $this->assertSame('value', $driver->getConfig('custom_option'));
        $this->assertNull($driver->getConfig('non_existent'));
        $this->assertSame('default', $driver->getConfig('non_existent', 'default'));

        // 测试设置单个配置
        $driver->setConfig('max_messages', 100);
        $this->assertSame(100, $driver->getConfig('max_messages'));

        // 测试设置多个配置
        $driver->setConfigs([
            'max_messages' => 200,
            'new_option' => 'new_value',
        ]);

        $this->assertSame(200, $driver->getConfig('max_messages'));
        $this->assertSame('new_value', $driver->getConfig('new_option'));
    }
}

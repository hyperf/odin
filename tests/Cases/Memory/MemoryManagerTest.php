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

namespace HyperfTest\Odin\Memory;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Memory\Driver\InMemoryDriver;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @coversNothing
 */
class MemoryManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructWithoutDriver()
    {
        $manager = new MemoryManager();

        $this->assertInstanceOf(MemoryManager::class, $manager);
        // 验证默认使用 InMemoryDriver
        $reflection = new ReflectionClass($manager);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $driver = $property->getValue($manager);

        $this->assertInstanceOf(InMemoryDriver::class, $driver);
    }

    public function testConstructWithCustomDriver()
    {
        $mockDriver = Mockery::mock(DriverInterface::class);
        $manager = new MemoryManager($mockDriver);

        $reflection = new ReflectionClass($manager);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $driver = $property->getValue($manager);

        $this->assertSame($mockDriver, $driver);
    }

    public function testAddMessage()
    {
        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('addMessage')
            ->once()
            ->with(Mockery::type(UserMessage::class));

        $manager = new MemoryManager($mockDriver);
        $message = new UserMessage('测试消息');

        $result = $manager->addMessage($message);

        $this->assertSame($manager, $result);
    }

    public function testAddSystemMessage()
    {
        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('addSystemMessage')
            ->once()
            ->with(Mockery::type(SystemMessage::class));

        $manager = new MemoryManager($mockDriver);
        $message = new SystemMessage('系统消息');

        $result = $manager->addSystemMessage($message);

        $this->assertSame($manager, $result);
    }

    public function testGetMessages()
    {
        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
        ];

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->once()
            ->andReturn($messages);

        $manager = new MemoryManager($mockDriver);
        $result = $manager->getMessages();

        $this->assertSame($messages, $result);
    }

    public function testGetSystemMessages()
    {
        $systemMessages = [
            new SystemMessage('系统消息1'),
            new SystemMessage('系统消息2'),
        ];

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getSystemMessages')
            ->once()
            ->andReturn($systemMessages);

        $manager = new MemoryManager($mockDriver);
        $result = $manager->getSystemMessages();

        $this->assertSame($systemMessages, $result);
    }

    public function testGetProcessedMessagesWithoutPolicy()
    {
        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
        ];

        $systemMessages = [
            new SystemMessage('系统消息1'),
        ];

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->once()
            ->andReturn($messages);
        $mockDriver->shouldReceive('getSystemMessages')
            ->once()
            ->andReturn($systemMessages);

        $manager = new MemoryManager($mockDriver);
        $result = $manager->getProcessedMessages();

        $expected = array_merge($systemMessages, $messages);
        $this->assertEquals($expected, $result);
    }

    public function testGetProcessedMessagesWithPolicy()
    {
        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
            new UserMessage('消息3'),
        ];

        $systemMessages = [
            new SystemMessage('系统消息1'),
            new SystemMessage('系统消息2'),
        ];

        $allMessages = array_merge([$systemMessages[1]], $messages);
        $processedMessages = array_slice($allMessages, -3); // 只保留最新的3条消息

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->once()
            ->andReturn($messages);
        $mockDriver->shouldReceive('getSystemMessages')
            ->once()
            ->andReturn($systemMessages);

        $mockPolicy = Mockery::mock(PolicyInterface::class);
        $mockPolicy->shouldReceive('process')
            ->once()
            ->with(Mockery::on(function ($arg) use ($allMessages) {
                return $arg == $allMessages;
            }))
            ->andReturn($processedMessages);

        $manager = new MemoryManager($mockDriver);
        $manager->setPolicy($mockPolicy);

        $result = $manager->getProcessedMessages();

        $this->assertSame($processedMessages, $result);
    }

    public function testGetProcessedMessagesCaching()
    {
        $messages = [new UserMessage('消息')];
        $systemMessages = [new SystemMessage('系统消息')];
        $allMessages = array_merge($systemMessages, $messages);

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->once() // 应该只调用一次
            ->andReturn($messages);
        $mockDriver->shouldReceive('getSystemMessages')
            ->once() // 应该只调用一次
            ->andReturn($systemMessages);

        $mockPolicy = Mockery::mock(PolicyInterface::class);
        $mockPolicy->shouldReceive('process')
            ->once() // 应该只调用一次
            ->with(Mockery::on(function ($arg) use ($allMessages) {
                return $arg == $allMessages;
            }))
            ->andReturn($allMessages);

        $manager = new MemoryManager($mockDriver);
        $manager->setPolicy($mockPolicy);

        // 第一次调用
        $manager->getProcessedMessages();

        // 第二次调用应该使用缓存
        $result = $manager->getProcessedMessages();

        $this->assertSame($allMessages, $result);
    }

    public function testClear()
    {
        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('clear')
            ->once();

        $manager = new MemoryManager($mockDriver);
        $result = $manager->clear();

        $this->assertSame($manager, $result);
    }

    public function testSetAndGetPolicy()
    {
        $policy = new LimitCountPolicy();

        $manager = new MemoryManager();
        $result = $manager->setPolicy($policy);

        $this->assertSame($manager, $result);
        $this->assertSame($policy, $manager->getPolicy());
    }

    public function testApplyPolicy()
    {
        $messages = [new UserMessage('消息')];
        $systemMessages = [new SystemMessage('系统消息')];
        $allMessages = array_merge($systemMessages, $messages);
        $processedMessages = array_slice($allMessages, 0, 1); // 只保留第一条消息

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->andReturn($messages);
        $mockDriver->shouldReceive('getSystemMessages')
            ->andReturn($systemMessages);

        $mockPolicy = Mockery::mock(PolicyInterface::class);
        $mockPolicy->shouldReceive('process')
            ->with(Mockery::on(function ($arg) use ($allMessages) {
                return $arg == $allMessages;
            }))
            ->andReturn($processedMessages);

        $manager = new MemoryManager($mockDriver);
        $manager->setPolicy($mockPolicy);

        // 获取处理后的消息
        $manager->getProcessedMessages();

        // 添加一条新消息
        $mockDriver->shouldReceive('addMessage')
            ->with(Mockery::type(UserMessage::class));
        $manager->addMessage(new UserMessage('新消息'));

        // 应用策略
        $result = $manager->applyPolicy();

        $this->assertSame($manager, $result);
    }
}

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

namespace HyperfTest\Odin\Cases\Message;

use Hyperf\Odin\Message\AbstractMessage;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 基础消息类测试.
 * @internal
 */
#[CoversClass(AbstractMessage::class)]
class AbstractMessageTest extends AbstractTestCase
{
    /**
     * 测试唯一标识功能.
     */
    public function testIdentifier()
    {
        // 使用具体实现类而不是抽象类的 mock
        $message = new SystemMessage('测试内容');

        // 默认标识为空字符串
        $this->assertSame('', $message->getIdentifier());

        // 设置标识后能正确获取
        $message->setIdentifier('test-id-123');
        $this->assertSame('test-id-123', $message->getIdentifier());

        // identifier 不应该出现在 toArray 结果中
        $array = $message->toArray();
        $this->assertArrayNotHasKey('identifier', $array);
    }

    /**
     * 测试角色功能.
     */
    public function testRole()
    {
        $message = new SystemMessage('测试内容');

        // 系统消息的角色是 system
        $this->assertSame(Role::System, $message->getRole());

        // 测试角色在 toArray 中正确返回
        $array = $message->toArray();
        $this->assertArrayHasKey('role', $array);
        $this->assertSame(Role::System->value, $array['role']);
    }

    /**
     * 测试内容和上下文功能.
     */
    public function testContentAndContext()
    {
        $content = '你好，{name}';
        $context = ['name' => '小明'];
        $message = new SystemMessage($content, $context);

        // 测试内容获取
        $this->assertSame($content, $message->getContent());

        // 测试上下文和内容数组形式
        $array = $message->toArray();
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertSame($content, $array['content']);

        // 测试上下文访问方法
        $this->assertSame('小明', $message->getContext('name'));
        $this->assertTrue($message->hasContext('name'));
        $this->assertFalse($message->hasContext('age'));
        $this->assertNull($message->getContext('age'));

        // 测试设置内容
        $message->setContent('新内容');
        $this->assertSame('新内容', $message->getContent());

        // 测试附加内容
        $message->appendContent('，你好');
        $this->assertSame('新内容，你好', $message->getContent());

        // 测试字符串转换(替换上下文变量)
        $testMessage = new SystemMessage($content, $context);
        $this->assertSame('你好，小明', (string) $testMessage);

        // 测试格式化内容
        $testFormatMessage = new SystemMessage($content, $context);
        $this->assertSame('你好，小红', $testFormatMessage->formatContent(['name' => '小红']));
    }

    /**
     * 测试从数组创建消息实例.
     */
    public function testFromArray()
    {
        $messageData = [
            'content' => '测试内容',
        ];

        $newMessage = SystemMessage::fromArray($messageData);

        $this->assertSame('测试内容', $newMessage->getContent());

        // 手动设置标识符
        $newMessage->setIdentifier('test-id');
        $this->assertSame('test-id', $newMessage->getIdentifier());
    }
}

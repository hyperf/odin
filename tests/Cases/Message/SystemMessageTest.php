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

use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 系统消息类测试.
 * @internal
 */
#[CoversClass(SystemMessage::class)]
class SystemMessageTest extends AbstractTestCase
{
    /**
     * 测试系统消息的角色.
     */
    public function testRole()
    {
        $message = new SystemMessage('系统指令');
        $this->assertSame(Role::System, $message->getRole());
    }

    /**
     * 测试系统消息的数组转换.
     */
    public function testToArray()
    {
        $message = new SystemMessage('系统指令');
        // 设置标识符，但不应出现在 toArray 中
        $message->setIdentifier('sys-123');

        $array = $message->toArray();

        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertSame(Role::System->value, $array['role']);
        $this->assertSame('系统指令', $array['content']);
    }

    /**
     * 测试从数组创建系统消息.
     */
    public function testFromArray()
    {
        $array = [
            'content' => '系统指令内容',
        ];

        $message = SystemMessage::fromArray($array);

        $this->assertInstanceOf(SystemMessage::class, $message);
        $this->assertSame('系统指令内容', $message->getContent());
        $this->assertSame('', $message->getIdentifier());
        $this->assertSame(Role::System, $message->getRole());

        // 手动设置标识符并验证
        $message->setIdentifier('sys-from-array');
        $this->assertSame('sys-from-array', $message->getIdentifier());
    }
}

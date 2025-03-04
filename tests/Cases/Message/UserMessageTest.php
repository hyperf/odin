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
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 用户消息类测试.
 * @internal
 */
#[CoversClass(UserMessage::class)]
class UserMessageTest extends AbstractTestCase
{
    /**
     * 测试用户消息的角色.
     */
    public function testRole()
    {
        $message = new UserMessage('用户消息');
        $this->assertSame(Role::User, $message->getRole());
    }

    /**
     * 测试简单文本内容的用户消息.
     */
    public function testSimpleContent()
    {
        $message = new UserMessage('用户消息内容');
        $this->assertSame('用户消息内容', $message->getContent());

        // 测试 toArray
        $array = $message->toArray();
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertSame(Role::User->value, $array['role']);
        $this->assertSame('用户消息内容', $array['content']);
    }

    /**
     * 测试多模态内容.
     */
    public function testMultimodalContent()
    {
        // 创建一个带有文本和图像的用户消息
        $message = new UserMessage();
        $message->addContent(UserMessageContent::text('这是文本内容'));
        $message->addContent(UserMessageContent::imageUrl('https://example.com/image.jpg'));

        // 测试内容列表
        $contents = $message->getContents();
        $this->assertIsArray($contents);
        $this->assertCount(2, $contents);

        // 测试 toArray
        $array = $message->toArray();
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertIsArray($array['content']);
        $this->assertCount(2, $array['content']);

        // 检查内容项的结构
        $this->assertSame('text', $array['content'][0]['type']);
        $this->assertSame('这是文本内容', $array['content'][0]['text']);
        $this->assertSame('image_url', $array['content'][1]['type']);
        $this->assertSame('https://example.com/image.jpg', $array['content'][1]['image_url']['url']);
    }

    /**
     * 测试从数组创建用户消息.
     */
    public function testFromArrayWithSimpleContent()
    {
        $array = [
            'content' => '用户消息内容',
        ];

        $message = UserMessage::fromArray($array);

        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertSame('用户消息内容', $message->getContent());
        $this->assertSame('', $message->getIdentifier());
        $this->assertNull($message->getContents()); // 简单内容不会创建 contents 数组

        // 手动设置标识符并验证
        $message->setIdentifier('user-123');
        $this->assertSame('user-123', $message->getIdentifier());
    }

    /**
     * 测试从数组创建带有多模态内容的用户消息.
     */
    public function testFromArrayWithMultimodalContent()
    {
        $array = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => '这是文本',
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'https://example.com/image.jpg',
                    ],
                ],
            ],
        ];

        $message = UserMessage::fromArray($array);

        $this->assertInstanceOf(UserMessage::class, $message);
        $this->assertSame('', $message->getIdentifier());

        // 手动设置标识符并验证
        $message->setIdentifier('user-multi-123');
        $this->assertSame('user-multi-123', $message->getIdentifier());

        // 检查多模态内容
        $contents = $message->getContents();
        $this->assertIsArray($contents);
        $this->assertCount(2, $contents);

        // 检查内容格式
        $contentArray = $message->toArray();
        $this->assertArrayHasKey('content', $contentArray);
        $this->assertIsArray($contentArray['content']);
        $this->assertCount(2, $contentArray['content']);
        $this->assertSame('text', $contentArray['content'][0]['type']);
        $this->assertSame('这是文本', $contentArray['content'][0]['text']);
    }
}

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

use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class UserMessageTest extends AbstractTestCase
{
    public function testSimple()
    {
        $message = new UserMessage('Hello, World!');
        $this->assertSame('Hello, World!', $message->getContent());
        $this->assertSame([
            'role' => 'user',
            'content' => 'Hello, World!',
        ], $message->toArray());
    }

    public function testAddContent()
    {
        $message = new UserMessage('');
        $content = UserMessageContent::text('Hello, World!');
        $message->addContent($content);

        $this->assertCount(1, $message->toArray());
        $this->assertSame('Hello, World!', $message->toArray()[0]['text']);
    }

    public function testToArray()
    {
        $message = new UserMessage('');
        $content1 = UserMessageContent::text('Hello, World!');
        $content2 = UserMessageContent::imageUrl('https://example.com/image.jpg');
        $message->addContent($content1)->addContent($content2);

        $expected = [
            [
                'type' => 'text',
                'text' => 'Hello, World!',
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'https://example.com/image.jpg',
                ],
            ],
        ];

        $this->assertSame($expected, $message->toArray());
    }

    public function testFromArray()
    {
        $data = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello, World!',
                ],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'https://example.com/image.jpg',
                    ],
                ],
            ],
        ];

        $message = UserMessage::fromArray($data);

        $this->assertCount(2, $message->toArray());
        $this->assertSame('Hello, World!', $message->toArray()[0]['text']);
        $this->assertSame('https://example.com/image.jpg', $message->toArray()[1]['image_url']['url']);
    }
}

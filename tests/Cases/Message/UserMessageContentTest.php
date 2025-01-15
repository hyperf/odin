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

use Hyperf\Odin\Message\UserMessageContent;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class UserMessageContentTest extends AbstractTestCase
{
    public function testText()
    {
        $content = UserMessageContent::text('text');
        $this->assertInstanceOf(UserMessageContent::class, $content);
        $this->assertSame('text', $content->getType());
    }

    public function testImageUrl()
    {
        $content = UserMessageContent::imageUrl('image_url');
        $this->assertInstanceOf(UserMessageContent::class, $content);
        $this->assertSame('image_url', $content->getType());
    }

    public function testSetText()
    {
        $content = new UserMessageContent('text');
        $content->setText('Hello, World!');
        $this->assertSame('Hello, World!', $content->getText());
    }

    public function testSetImageUrl()
    {
        $content = new UserMessageContent('image_url');
        $content->setImageUrl('https://example.com/image.jpg');
        $this->assertSame('https://example.com/image.jpg', $content->getImageUrl());
    }

    public function testIsValid()
    {
        $textContent = new UserMessageContent('text');
        $textContent->setText('Hello, World!');
        $this->assertTrue($textContent->isValid());

        $imageContent = new UserMessageContent('image_url');
        $imageContent->setImageUrl('https://example.com/image.jpg');
        $this->assertTrue($imageContent->isValid());

        $invalidContent = new UserMessageContent('text');
        $this->assertFalse($invalidContent->isValid());
    }

    public function testToArray()
    {
        $textContent = new UserMessageContent('text');
        $textContent->setText('Hello, World!');
        $this->assertSame([
            'type' => 'text',
            'text' => 'Hello, World!',
        ], $textContent->toArray());

        $imageContent = new UserMessageContent('image_url');
        $imageContent->setImageUrl('https://example.com/image.jpg');
        $this->assertSame([
            'type' => 'image_url',
            'image_url' => [
                'url' => 'https://example.com/image.jpg',
            ],
        ], $imageContent->toArray());
    }
}

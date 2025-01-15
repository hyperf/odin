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

namespace Hyperf\Odin\Message;

class UserMessageContent
{
    private string $type;

    private string $text = '';

    private string $imageUrl = '';

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function text(string $text): self
    {
        return (new self('text'))->setText($text);
    }

    public static function imageUrl(string $url): self
    {
        return (new self('image_url'))->setImageUrl($url);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function isValid(): bool
    {
        return match ($this->type) {
            'text' => $this->text !== '',
            'image_url' => $this->imageUrl !== '',
            default => false,
        };
    }

    public function toArray(): array
    {
        return match ($this->type) {
            'text' => [
                'type' => 'text',
                'text' => $this->text,
            ],
            'image_url' => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $this->imageUrl,
                ],
            ],
            default => [],
        };
    }
}

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
    public const TEXT = 'text';

    public const IMAGE_URL = 'image_url';

    private string $type;

    private string $text = '';

    /**
     * 可以是链接，可以是 base64.
     */
    private string $imageUrl = '';

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function text(string $text): self
    {
        return (new self(self::TEXT))->setText($text);
    }

    public static function imageUrl(string $url): self
    {
        return (new self(self::IMAGE_URL))->setImageUrl($url);
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
        $this->text = trim($text);
        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = trim($imageUrl);
        return $this;
    }

    public function isValid(): bool
    {
        return match ($this->type) {
            self::TEXT => $this->text !== '',
            self::IMAGE_URL => $this->imageUrl !== '',
            default => false,
        };
    }

    public function toArray(): array
    {
        return match ($this->type) {
            self::TEXT => [
                'type' => 'text',
                'text' => $this->text,
            ],
            self::IMAGE_URL => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $this->imageUrl,
                ],
            ],
            default => [],
        };
    }
}

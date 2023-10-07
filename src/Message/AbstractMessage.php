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

use Stringable;

abstract class AbstractMessage implements MessageInterface, Stringable
{
    protected Role $role;

    protected string $content = '';

    protected array $context = [];

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->getContent();
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }

    public static function fromArray(array $message): static
    {
        return new static($message['content'] ?? '');
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function appendContent(string $content): static
    {
        $this->content .= $content;
        return $this;
    }

    public function getContext(string $key): mixed
    {
        return $this->context[$key] ?? null;
    }

    public function setContext(string $key, $value): mixed
    {
        $this->context[$key] = $value;
        return $value;
    }
}

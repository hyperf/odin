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

    public function __construct(string $content, array $context = [])
    {
        $this->content = $content;
        $this->context = $context;
    }

    public function __toString(): string
    {
        // Replace the variables in content according to the key in context, for example {name} matches $context['name']
        $content = $this->content;
        foreach ($this->context as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    public function formatContent(array $context): string
    {
        $context = array_merge($this->context, $context);
        $content = $this->content;
        foreach ($context as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
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

    public function hasContext(string $key): bool
    {
        return isset($this->context[$key]);
    }
}

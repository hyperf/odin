<?php

namespace Hyperf\Odin\Message;


use Stringable;

abstract class AbstractMessage implements MessageInterface, Stringable
{

    protected Role $role;
    protected string $content = '';

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }

    public function __toString(): string
    {
        return $this->getContent();
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

}
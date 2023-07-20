<?php

namespace Hyperf\Odin\Message;


abstract class AbstractMessage implements MessageInterface
{

    protected Role $role;
    protected string $message = '';

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->message,
        ];
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->message;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function setContent(string $content): static
    {
        $this->message = $content;
        return $this;
    }

    public function appendContent(string $content): static
    {
        $this->message .= $content;
        return $this;
    }

}
<?php

namespace Hyperf\Odin\Apis;

interface MessageInterface
{

    public function getRole(): Role;
    public function getContent(): string;
    public function setRole(Role $role): static;
    public function setContent(string $content): static;
    public function toArray(): array;
}
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

namespace Hyperf\Odin\Api\Response;

class Model
{
    public function __construct(
        public string $id,
        public int $created,
        public string $ownedBy,
        public ?array $permission,
        public string $root,
        public ?string $parent
    ) {}

    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['created'], $data['owned_by'], $data['permissions'], $data['root'], $data['parent'] ?? null);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function setCreated(int $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getOwnedBy(): string
    {
        return $this->ownedBy;
    }

    public function setOwnedBy(string $ownedBy): self
    {
        $this->ownedBy = $ownedBy;
        return $this;
    }

    public function getPermission(): array
    {
        return $this->permission;
    }

    public function setPermission(array $permission): self
    {
        $this->permission = $permission;
        return $this;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function setRoot(string $root): self
    {
        $this->root = $root;
        return $this;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function setParent(?string $parent): self
    {
        $this->parent = $parent;
        return $this;
    }
}

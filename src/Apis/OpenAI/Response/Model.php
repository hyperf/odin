<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


class Model
{

    public function __construct(
        public string $id,
        public int $created,
        public string $ownedBy,
        public ?array $permission,
        public string $root,
        public ?string $parent
    ) {

    }

    public static function fromArray(array $data): static
    {
        return new static($data['id'], $data['created'], $data['owned_by'], $data['permissions'], $data['root'], $data['parent'] ?? null);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function setCreated(int $created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getOwnedBy(): string
    {
        return $this->ownedBy;
    }

    public function setOwnedBy(string $ownedBy): static
    {
        $this->ownedBy = $ownedBy;
        return $this;
    }

    public function getPermission(): array
    {
        return $this->permission;
    }

    public function setPermission(array $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function setRoot(string $root): static
    {
        $this->root = $root;
        return $this;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function setParent(?string $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

}
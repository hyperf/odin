<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


class Embedding
{
    public function __construct(public array $embedding, public int $index)
    {

    }

    public static function fromArray(array $data): static
    {
        return new static(embedding: $data['embedding'], index: $data['index']);
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function setEmbedding(array $embedding): static
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): static
    {
        $this->index = $index;
        return $this;
    }
}
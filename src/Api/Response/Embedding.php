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

class Embedding
{
    public function __construct(public array $embedding, public int $index) {}

    public static function fromArray(array $data): self
    {
        return new self(embedding: $data['embedding'], index: $data['index']);
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function setEmbedding(array $embedding): self
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'object' => 'embedding',
            'embedding' => $this->embedding,
            'index' => $this->index,
        ];
    }
}

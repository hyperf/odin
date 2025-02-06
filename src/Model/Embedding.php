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

namespace Hyperf\Odin\Model;

class Embedding
{
    public function __construct(public array $embeddings) {}

    public function getEmbeddings(): array
    {
        return $this->embeddings;
    }
}

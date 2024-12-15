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

interface EmbeddingInterface
{
    public function embedding(string $input): Embedding;

    public function getModelName(): string;

    public function getVectorSize(): int;
}

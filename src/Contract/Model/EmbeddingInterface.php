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

namespace Hyperf\Odin\Contract\Model;

use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Model\Embedding;

interface EmbeddingInterface
{
    public function embedding(string $input): Embedding;

    public function embeddings(array|string $input, ?string $encoding_format = 'float', ?string $user = null): EmbeddingResponse;

    public function getModelName(): string;

    public function getVectorSize(): int;
}

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

namespace Hyperf\Odin\Knowledge;

use Hyperf\Odin\Document\Document;
use Hyperf\Odin\Model\EmbeddingInterface;
use Hyperf\Odin\VectorStore\Qdrant\Qdrant;
use Hyperf\Qdrant\Struct\UpdateResult;

class Knowledge
{
    public $vectorSizeMap
        = [
            'qwen:32b-chat' => 5120,
        ];

    public function __construct(public EmbeddingInterface $embeddingModel, protected Qdrant $qdrant)
    {
    }

    public function similaritySearch(string $query, string $collection, int $limit = 5, float $score = 0.1): array
    {
        return $this->qdrant->search(query: $query, collectionName: $collection, model: $this->embeddingModel, limit: $limit, score: $score);
    }

    public function upsert(string $collection, Document $document): ?UpdateResult
    {
        $this->qdrant->getOrCreateCollection($collection, $this->vectorSizeMap[$this->embeddingModel->getSpecifiedModelName()]);
        return $this->qdrant->upsertPointsByDocument($collection, $document, $this->embeddingModel);
    }
}

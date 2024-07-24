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

namespace Hyperf\Odin\VectorStore\Qdrant;

use GuzzleHttp\Exception\ClientException;
use Hyperf\Odin\Document\Document;
use Hyperf\Odin\Model\EmbeddingInterface;
use Hyperf\Qdrant\Api\Collections;
use Hyperf\Qdrant\Api\Points;
use Hyperf\Qdrant\Struct\Collections\CollectionInfo;
use Hyperf\Qdrant\Struct\Collections\Enums\Distance;
use Hyperf\Qdrant\Struct\Collections\VectorParams;
use Hyperf\Qdrant\Struct\Points\ExtendedPointId;
use Hyperf\Qdrant\Struct\Points\ExtendedPointIds;
use Hyperf\Qdrant\Struct\Points\Point\PointStruct;
use Hyperf\Qdrant\Struct\Points\Point\Record;
use Hyperf\Qdrant\Struct\Points\Point\ScoredPoint;
use Hyperf\Qdrant\Struct\Points\VectorStruct;
use Hyperf\Qdrant\Struct\Points\WithPayload;
use Hyperf\Qdrant\Struct\Points\WithVector;
use Hyperf\Qdrant\Struct\UpdateResult;
use RuntimeException;

class Qdrant
{
    public function __construct(protected Points $points, protected Collections $collections)
    {
    }

    public function search(
        string $query,
        string $collectionName,
        EmbeddingInterface $model,
        int $limit = 5,
        float $score = 0.4
    ): array {
        $vector = new VectorStruct($model->embedding($query)->getEmbeddings());
        $result = $this->points->searchPoints(collectionName: $collectionName, vector: $vector, limit: $limit, withPayload: new WithPayload(true));
        $result = array_map(function (ScoredPoint $scoredPoint, int $key) use ($score) {
            if ($scoredPoint->score > $score) {
                return [
                    'id' => $scoredPoint->id->id,
                    'version' => $scoredPoint->version,
                    'score' => $scoredPoint->score,
                    'payload' => $scoredPoint->payload,
                ];
            }
        }, $result, array_keys($result));
        return array_filter($result);
    }

    public function getOrCreateCollection(string $name, int $vectorSize = 1536): CollectionInfo
    {
        try {
            $collectionInfo = $this->getCollectionInfo($name);
        } catch (ClientException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
            $result = $this->createCollection($name, $vectorSize);
            if (! $result) {
                throw new RuntimeException(sprintf('Failed to create collection %s.', $name));
            }
            $collectionInfo = $this->getCollectionInfo($name);
        }
        return $collectionInfo;
    }

    public function getCollectionInfo(string $name): CollectionInfo
    {
        return $this->collections->getCollectionInfo($name);
    }

    public function createCollection(string $name, int $vectorSize = 1536, $vectorDistance = Distance::COSINE): bool
    {
        return $this->collections->createCollection($name, new VectorParams($vectorSize, $vectorDistance));
    }

    public function getPoint(string $collectionName, ExtendedPointId $pointId): Record
    {
        return $this->points->getPoint($collectionName, $pointId);
    }

    public function getPoints(
        string $collectionName,
        array $pointIds,
        bool $withPayload = true,
        bool $withVector = false
    ): array {
        $pointIds = new ExtendedPointIds($pointIds);
        return $this->points->getPoints($collectionName, $pointIds, new WithPayload($withPayload), new WithVector($withVector));
    }

    public function scrollPoints(
        string $collectionName,
        ?ExtendedPointId $offset = null,
        int $limit = 10,
    ): array {
        return $this->points->scrollPoints(collectionName: $collectionName, offset: $offset, limit: $limit, withPayload: new WithPayload(true), withVector: new WithVector(false),);
    }

    public function upsertPointsByDocument(
        string $collectionName,
        Document $document,
        EmbeddingInterface $embeddingModel,
        bool $wait = true
    ): ?UpdateResult {
        $splitBlocks = $document->split();
        $pointStructs = array_map(function (string $block) use ($embeddingModel, $document, $collectionName) {
            $pointId = $this->generatePointId($block);
            $pointId = new ExtendedPointId($pointId);
            try {
                $point = $this->getPoint($collectionName, $pointId);
                return null;
            } catch (ClientException $exception) {
                if ($exception->getCode() !== 404) {
                    throw $exception;
                }
                $embedding = $embeddingModel->embedding($block)->getEmbeddings();
                $payload = array_merge([
                    '__content__' => $block,
                    '__model__' => $embeddingModel->getSpecifiedModelName()
                ], $document->getMetadata());
                return new PointStruct($pointId, new VectorStruct($embedding), $payload);
            }
        }, $splitBlocks, array_keys($splitBlocks));
        $pointStructs = array_filter($pointStructs);
        if (! $pointStructs) {
            return null;
        }
        $this->points->setWait($wait);
        return $this->points->upsertPoints($collectionName, $pointStructs);
    }

    protected function generatePointId(string $text): string
    {
        return md5($text);
    }
}

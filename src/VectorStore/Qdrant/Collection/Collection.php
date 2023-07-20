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
namespace Hyperf\Odin\VectorStore\Qdrant\Collection;

class Collection
{
    protected string $status;

    protected string $optimizerStatus;

    protected int $vectorsCount;

    protected int $pointsCount;

    protected int $segmentsCount;

    protected int $indexedVectorsCount;

    protected mixed $config;

    protected mixed $payloadSchema;

    public function __construct(array $values)
    {
        $this->status = $values['status'];
        $this->optimizerStatus = $values['optimizer_status'];
        $this->vectorsCount = $values['vectors_count'];
        $this->indexedVectorsCount = $values['indexed_vectors_count'];
        $this->pointsCount = $values['points_count'];
        $this->segmentsCount = $values['segments_count'];

        # TODO: 没数据没定义格式
        $this->payloadSchema = $values['payload_schema'];
        $this->config = new Config($values['config']);
    }
}

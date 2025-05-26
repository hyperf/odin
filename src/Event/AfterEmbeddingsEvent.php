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

namespace Hyperf\Odin\Event;

use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\Response\EmbeddingResponse;

class AfterEmbeddingsEvent
{
    private EmbeddingRequest $embeddingRequest;

    private EmbeddingResponse $embeddingResponse;

    private float $duration;

    public function __construct(
        EmbeddingRequest $embeddingRequest,
        EmbeddingResponse $embeddingResponse,
        float $duration,
    ) {
        $this->embeddingRequest = $embeddingRequest;
        $this->setEmbeddingResponse($embeddingResponse);
        $this->duration = $duration;
    }

    public function getEmbeddingRequest(): EmbeddingRequest
    {
        return $this->embeddingRequest;
    }

    public function getEmbeddingResponse(): EmbeddingResponse
    {
        return $this->embeddingResponse;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function setEmbeddingResponse(EmbeddingResponse $embeddingResponse): void
    {
        $embeddingResponse = clone $embeddingResponse;
        $embeddingResponse->removeBigObject();
        $this->embeddingResponse = $embeddingResponse;
    }
}

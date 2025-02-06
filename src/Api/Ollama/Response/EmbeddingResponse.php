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

namespace Hyperf\Odin\Api\Ollama\Response;

use GuzzleHttp\Psr7\Response;
use Hyperf\Contract\Arrayable;
use RuntimeException;

class EmbeddingResponse implements Arrayable
{
    protected array $embeddings = [];

    public function __construct(Response $response)
    {
        $decoded = json_decode($response->getBody()->getContents(), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['embedding'])) {
            throw new RuntimeException('Failed to decode response body.');
        }
        $this->setEmbeddings($decoded['embedding']);
    }

    public static function fromArray(array $data): self
    {
        return new self($data['embeddings']);
    }

    public function toArray(): array
    {
        return $this->getEmbeddings();
    }

    public function getEmbeddings(): array
    {
        return $this->embeddings;
    }

    public function setEmbeddings(array $embeddings): void
    {
        $this->embeddings = $embeddings;
    }
}

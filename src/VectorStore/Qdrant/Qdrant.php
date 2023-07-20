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

use Exception;
use Hyperf\Codec\Json;
use Hyperf\Odin\VectorStore\Qdrant\Collection\Collection;
use Psr\Http\Client\ClientInterface;

class Qdrant
{
    public function __construct(protected ClientInterface $client)
    {
    }

    public function getCollectionList(): array
    {
        $result = $this->request('/collections');
        return array_column($result['collections'], 'name');
    }

    public function getCollectionInfo(string $name): Collection
    {
        $result = $this->request("/collections/{$name}");
        return new Collection($result);
    }

    protected function request(string $uri): mixed
    {
        $result = $this->client->get($uri);
        $result = Json::decode($result->getBody()->getContents());
        if ($result['status'] !== 'ok') {
            throw new Exception($result['status']['error']);
        }
        return $result['result'];
    }
}

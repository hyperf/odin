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

use Hyperf\Qdrant\Api\Collections;
use Hyperf\Qdrant\Api\Points;
use Hyperf\Qdrant\Connection\HttpClient;
use Psr\Container\ContainerInterface;

class QdrantFactory
{
    public function __invoke(ContainerInterface $container): Qdrant
    {
        $httpClient = new HttpClient(new Config());
        $points = new Points($httpClient);
        $collections = new Collections($httpClient);
        return new Qdrant($points, $collections);
    }
}

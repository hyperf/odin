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
namespace Hyperf\Odin\Collection;

use Exception;
use Hyperf\Odin\VectorStore\VectorStore;

abstract class AbstractCollection
{
    protected string $name;

    protected int $size = 1536;

    protected Distance $distance = Distance::COSINE;

    protected string $connection = 'default';

    protected VectorStore $vectorStore;

    public function __construct()
    {
        if (! $this->name) {
            throw new Exception('Collection name is required.');
        }
    }
}

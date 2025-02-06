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

use Hyperf\Qdrant\ConfigInterface;

class Config implements ConfigInterface
{
    public function __construct(
        protected string $host = '127.0.0.1',
        protected int $port = 6333,
        protected string $scheme = 'http',
    ) {}

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}

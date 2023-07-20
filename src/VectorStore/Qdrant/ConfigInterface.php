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

interface ConfigInterface
{
    public function getScheme(): string;

    public function getHost(): string;

    public function getPort(): int;
}

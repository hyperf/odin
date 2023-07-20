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

enum Distance: string
{
    case COSINE = 'Cosine';
    case EUCLID = 'Euclid';
    case DOT = 'Dot';
}

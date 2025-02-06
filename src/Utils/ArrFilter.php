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

namespace Hyperf\Odin\Utils;

class ArrFilter
{
    public static function filterInstance(string $class, array $instances): array
    {
        $result = [];
        foreach ($instances as $instance) {
            if ($instance instanceof $class) {
                $result[] = $instance;
            }
        }
        return $result;
    }
}

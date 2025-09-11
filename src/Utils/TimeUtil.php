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

/**
 * 时间工具类，用于统一处理时间计算.
 */
class TimeUtil
{
    /**
     * 计算时间间隔（毫秒）.
     *
     * @param float $startTime 开始时间（microtime(true)）
     * @param int $precision 精度，保留小数位数，默认不保留小数
     * @return float 时间间隔（毫秒）
     */
    public static function calculateDurationMs(float $startTime, int $precision = 0): float
    {
        return round((microtime(true) - $startTime) * 1000, $precision);
    }

    /**
     * 计算两个时间点之间的间隔（毫秒）.
     *
     * @param float $startTime 开始时间（microtime(true)）
     * @param float $endTime 结束时间（microtime(true)）
     * @param int $precision 精度，保留小数位数，默认不保留小数
     * @return float 时间间隔（毫秒）
     */
    public static function calculateIntervalMs(float $startTime, float $endTime, int $precision = 0): float
    {
        return round(($endTime - $startTime) * 1000, $precision);
    }

    /**
     * 获取当前时间戳（microtime格式）.
     *
     * @return float 当前时间戳
     */
    public static function now(): float
    {
        return microtime(true);
    }
}

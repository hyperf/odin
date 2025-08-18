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

class LogUtil
{
    /**
     * 特殊标识，用于表示字段不存在.
     */
    private const FIELD_NOT_EXISTS = '___FIELD_NOT_EXISTS___';

    /**
     * 性能标记常量.
     */
    private const PERF_NORMAL = 'NORMAL';

    private const PERF_SLOW = 'SLOW';

    private const PERF_VERY_SLOW = 'VERY_SLOW';

    private const PERF_EXTREMELY_SLOW = 'EXTREMELY_SLOW';

    private const PERF_CRITICALLY_SLOW = 'CRITICALLY_SLOW';

    private const PERF_TIMEOUT_RISK = 'TIMEOUT_RISK';

    /**
     * 递归处理数组，格式化超长文本和二进制数据.
     */
    public static function formatLongText(array $args): array
    {
        return self::recursiveFormat($args);
    }

    /**
     * 根据白名单过滤日志数据并格式化.
     *
     * @param array $logData 原始日志数据
     * @param array $whitelistFields 白名单字段列表，为空则返回所有字段，支持嵌套字段如 'args.messages'
     * @param bool $enableWhitelist 是否启用白名单过滤，默认false
     * @return array 过滤并格式化后的日志数据
     */
    public static function filterAndFormatLogData(array $logData, array $whitelistFields = [], bool $enableWhitelist = false): array
    {
        // 如果未启用白名单或白名单为空，处理所有字段
        if (! $enableWhitelist || empty($whitelistFields)) {
            return self::formatLongText($logData);
        }

        // 根据白名单过滤字段，支持嵌套字段
        $filteredData = [];
        foreach ($whitelistFields as $field) {
            $value = self::getNestedValue($logData, $field);
            if ($value !== self::FIELD_NOT_EXISTS) { // 如果字段存在，则添加到结果中
                self::setNestedValue($filteredData, $field, $value);
            }
        }

        // 添加特殊字段，这些字段不参与白名单过滤，总是完整记录
        $specialFields = ['response_headers', 'headers'];
        foreach ($specialFields as $specialField) {
            if (array_key_exists($specialField, $logData)) {
                $filteredData[$specialField] = $logData[$specialField];
            }
        }

        // 格式化过滤后的数据
        return self::formatLongText($filteredData);
    }

    /**
     * 根据耗时生成性能标记.
     *
     * @param float|int $durationMs 耗时（毫秒）
     * @return string 性能标记，总是返回标记字符串
     */
    public static function getPerformanceFlag(float|int $durationMs): string
    {
        // 转换为秒
        $durationSec = $durationMs / 1000;

        if ($durationSec > 1200) { // > 20分钟
            return self::PERF_TIMEOUT_RISK;
        }
        if ($durationSec > 900) { // > 15分钟
            return self::PERF_CRITICALLY_SLOW;
        }
        if ($durationSec > 600) { // > 10分钟
            return self::PERF_EXTREMELY_SLOW;
        }
        if ($durationSec > 300) { // > 5分钟
            return self::PERF_VERY_SLOW;
        }
        if ($durationSec > 180) { // > 3分钟
            return self::PERF_SLOW;
        }

        return self::PERF_NORMAL; // <= 3分钟，正常
    }

    /**
     * 根据嵌套路径获取数组中的值.
     *
     * @param array $data 数据数组
     * @param string $path 路径，支持点语法如 'args.messages'
     * @return mixed 找到的值，不存在则返回特殊标识字符串
     */
    private static function getNestedValue(array $data, string $path): mixed
    {
        // 如果路径不包含点，直接返回顶级字段
        if (strpos($path, '.') === false) {
            return array_key_exists($path, $data) ? $data[$path] : self::FIELD_NOT_EXISTS;
        }

        // 处理嵌套路径
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return self::FIELD_NOT_EXISTS;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * 根据嵌套路径设置数组中的值.
     *
     * @param array $data 目标数组
     * @param string $path 路径，支持点语法如 'args.messages'
     * @param mixed $value 要设置的值
     */
    private static function setNestedValue(array &$data, string $path, mixed $value): void
    {
        // 如果路径不包含点，直接设置顶级字段
        if (strpos($path, '.') === false) {
            $data[$path] = $value;
            return;
        }

        // 处理嵌套路径
        $keys = explode('.', $path);
        $current = &$data;

        $lastKey = array_pop($keys);
        foreach ($keys as $key) {
            if (! isset($current[$key]) || ! is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current[$lastKey] = $value;
    }

    /**
     * 递归处理数组中的每个元素.
     */
    private static function recursiveFormat(mixed $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::recursiveFormat($value);
            }
            return $data;
        }
        if (is_object($data)) {
            // 对象转换为数组再处理，最后转回对象
            if (method_exists($data, 'toArray')) {
                $array = $data->toArray();
                $array = self::recursiveFormat($array);
                // 如果对象有 fromArray 方法，可以使用它恢复对象
                if (method_exists($data, 'fromArray')) {
                    return $data->fromArray($array);
                }
                return $array;
            }
            return $data;
        }
        if (is_string($data)) {
            // 处理二进制数据
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $data)) {
                return '[Binary Data]';
            }

            // 检测是否为base64图片
            if (preg_match('/^data:image\/[a-zA-Z]+;base64,/', $data)) {
                return '[Base64 Image]';
            }

            // 处理超长字符串
            if (strlen($data) > 2000) {
                return '[Long Text]';
            }
        }

        return $data;
    }
}

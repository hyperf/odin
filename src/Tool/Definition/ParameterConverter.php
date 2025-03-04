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

namespace Hyperf\Odin\Tool\Definition;

use InvalidArgumentException;

/**
 * 参数转换器，用于自动将字符串类型的参数转换为目标类型.
 */
class ParameterConverter
{
    /**
     * 根据 JSON Schema 类型转换参数.
     *
     * @param mixed $value 原始值
     * @param array|string $type 目标类型，可以是 string|number|integer|boolean|array|object 或类型数组
     * @param array $schema 完整的参数 schema，包含额外信息如 format
     * @return mixed 转换后的值
     */
    public static function convert(mixed $value, $type, array $schema = [])
    {
        // 如果值已经是 null，且类型允许 null，则直接返回
        if ($value === null) {
            if ($type === 'null' || (is_array($type) && in_array('null', $type))) {
                return null;
            }
        }

        // 如果类型是数组，尝试匹配最合适的类型
        if (is_array($type)) {
            // 如果原始值已经匹配数组中的某个类型，则直接返回
            $valueType = self::getType($value);
            if (in_array($valueType, $type)) {
                return $value;
            }

            // 尝试使用数组中第一个非 null 类型进行转换
            foreach ($type as $t) {
                if ($t !== 'null') {
                    return self::convert($value, $t, $schema);
                }
            }

            return $value; // 如果只有 null 类型，返回原值
        }

        // 根据目标类型转换
        switch ($type) {
            case 'string':
                return self::toString($value, $schema);
            case 'number':
                return self::toNumber($value);
            case 'integer':
                return self::toInteger($value);
            case 'boolean':
                return self::toBoolean($value);
            case 'array':
                return self::toArray($value, $schema);
            case 'object':
                return self::toObject($value, $schema);
            default:
                return $value; // 未知类型，返回原值
        }
    }

    /**
     * 转换为字符串类型.
     *
     * @param mixed $value 原始值
     * @param array $schema 参数 schema
     * @return string 转换后的字符串
     */
    public static function toString($value, array $schema = []): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * 转换为数字类型.
     *
     * @param mixed $value 原始值
     * @return float 转换后的数字
     */
    public static function toNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if (strtolower($value) === 'true') {
                return 1.0;
            }
            if (strtolower($value) === 'false') {
                return 0.0;
            }
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        throw new InvalidArgumentException('Cannot convert value to number: ' . self::valueToString($value));
    }

    /**
     * 转换为整数类型.
     *
     * @param mixed $value 原始值
     * @return int 转换后的整数
     */
    public static function toInteger($value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            $value = trim($value);
            if (strtolower($value) === 'true') {
                return 1;
            }
            if (strtolower($value) === 'false') {
                return 0;
            }
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        if (is_float($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Cannot convert value to integer: ' . self::valueToString($value));
    }

    /**
     * 转换为布尔类型.
     *
     * @param mixed $value 原始值
     * @return bool 转换后的布尔值
     */
    public static function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim(strtolower($value));
            if ($value === 'true' || $value === 'yes' || $value === '1' || $value === 'on') {
                return true;
            }
            if ($value === 'false' || $value === 'no' || $value === '0' || $value === 'off' || $value === '') {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return ! empty($value);
    }

    /**
     * 转换为数组类型.
     *
     * @param mixed $value 原始值
     * @param array $schema 参数 schema，包含 items 定义
     * @return array 转换后的数组
     */
    public static function toArray($value, array $schema = []): array
    {
        if (is_array($value)) {
            // 如果已经是数组，但需要转换元素类型
            if (isset($schema['items']) && is_array($schema['items']) && isset($schema['items']['type'])) {
                $itemType = $schema['items']['type'];
                foreach ($value as $key => $item) {
                    $value[$key] = self::convert($item, $itemType, $schema['items']);
                }
            }
            return $value;
        }

        if (is_string($value)) {
            // 尝试解析 JSON 字符串
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // 成功解析为数组，递归处理
                return self::toArray($decoded, $schema);
            }

            // 如果不是 JSON，按逗号分隔
            $parts = array_map('trim', explode(',', $value));

            // 如果需要转换元素类型
            if (isset($schema['items']) && is_array($schema['items']) && isset($schema['items']['type'])) {
                $itemType = $schema['items']['type'];
                foreach ($parts as $key => $part) {
                    $parts[$key] = self::convert($part, $itemType, $schema['items']);
                }
            }

            return $parts;
        }

        // 如果是标量值，包装为单元素数组
        if (is_scalar($value) || is_null($value)) {
            return [$value];
        }

        // 尝试转换对象为数组
        if (is_object($value)) {
            return (array) $value;
        }

        throw new InvalidArgumentException('Cannot convert value to array: ' . self::valueToString($value));
    }

    /**
     * 转换为对象类型.
     *
     * @param mixed $value 原始值
     * @param array $schema 参数 schema，包含 properties 定义
     * @return array|object 转换后的对象（PHP 中使用关联数组表示）
     */
    public static function toObject($value, array $schema = [])
    {
        if (is_array($value) && ! self::isIndexedArray($value)) {
            // 如果已经是关联数组，但需要转换属性类型
            if (isset($schema['properties']) && is_array($schema['properties'])) {
                foreach ($schema['properties'] as $propName => $propSchema) {
                    if (array_key_exists($propName, $value)) {
                        $value[$propName] = self::convert(
                            $value[$propName],
                            $propSchema['type'] ?? 'string',
                            $propSchema
                        );
                    }
                }
            }
            return $value;
        }

        if (is_object($value)) {
            // 转换为数组后递归处理
            return self::toObject((array) $value, $schema);
        }

        if (is_string($value)) {
            // 尝试解析 JSON 字符串
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && ! self::isIndexedArray($decoded)) {
                // 成功解析为对象，递归处理
                return self::toObject($decoded, $schema);
            }
        }

        // 如果需要强制转换为对象，可以将标量值包装在默认属性中
        if (is_scalar($value) || is_null($value)) {
            return ['value' => $value];
        }

        throw new InvalidArgumentException('Cannot convert value to object: ' . self::valueToString($value));
    }

    /**
     * 判断数组是否为索引数组（非关联数组）.
     *
     * @param array $array 要检查的数组
     * @return bool 是否为索引数组
     */
    private static function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * 获取值的 PHP 类型，映射到 JSON Schema 类型.
     *
     * @param mixed $value 要检查的值
     * @return string JSON Schema 类型
     */
    private static function getType($value): string
    {
        if (is_string($value)) {
            return 'string';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'number';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_array($value)) {
            return self::isIndexedArray($value) ? 'array' : 'object';
        }

        if (is_object($value)) {
            return 'object';
        }

        if (is_null($value)) {
            return 'null';
        }

        return 'string'; // 默认类型
    }

    /**
     * 将值转换为字符串表示，用于错误消息.
     *
     * @param mixed $value 要转换的值
     * @return string 字符串表示
     */
    private static function valueToString($value): string
    {
        if (is_scalar($value) || is_null($value)) {
            return var_export($value, true);
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            return 'object of class ' . get_class($value);
        }

        return 'unknown type';
    }
}

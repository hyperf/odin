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
     * 递归处理数组，格式化超长文本和二进制数据.
     */
    public static function formatLongText(array $args): array
    {
        return self::recursiveFormat($args);
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
            if (strlen($data) > 1000) {
                return '[Long Text]';
            }
        }

        return $data;
    }
}

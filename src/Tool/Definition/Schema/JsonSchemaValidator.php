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

namespace Hyperf\Odin\Tool\Definition\Schema;

use InvalidArgumentException;

/**
 * JSON Schema 验证器，用于验证数据是否符合 JSON Schema 规范.
 */
class JsonSchemaValidator
{
    /**
     * @var array 验证错误信息
     */
    protected array $errors = [];

    /**
     * 验证数据是否符合指定的 Schema.
     */
    public function validate(array $data, array $schema): bool
    {
        $this->errors = [];
        $this->validateSchema($data, $schema);
        return empty($this->errors);
    }

    /**
     * 获取验证错误信息.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 验证数据.
     * @param mixed $data
     */
    protected function validateSchema($data, array $schema, ?string $path = null): void
    {
        if (! isset($schema['type'])) {
            throw new InvalidArgumentException('Schema must have a type');
        }

        $type = $schema['type'];

        // 处理类型是数组的情况（如 ["string", "object", "array"]）
        if (is_array($type)) {
            // 检查数据类型是否在允许的类型列表中
            foreach ($type as $allowedType) {
                if ($this->checkType($data, $allowedType)) {
                    // 找到匹配的类型，使用该类型进行验证
                    $type = $allowedType;
                    break;
                }
            }

            // 如果没有找到匹配的类型，使用数组中的第一个类型进行验证
            if (is_array($type)) {
                $type = $type[0];
            }
        }

        $method = 'validate' . ucfirst($type);

        if (! method_exists($this, $method)) {
            throw new InvalidArgumentException('Unsupported schema type: ' . $type);
        }

        $this->{$method}($data, $schema, $path);
    }

    /**
     * 验证对象类型.
     * @param mixed $data
     */
    protected function validateObject($data, array $schema, ?string $path = null): void
    {
        // 修复此处的判断逻辑，考虑 PHP 关联数组作为 JSON 对象
        // 检查是否是关联数组（对象）或标准对象
        if (! is_array($data) && ! is_object($data)) {
            $this->addError($path, 'Must be an object');
            return;
        }

        // 如果是索引数组（非关联数组），则视为验证失败
        if (is_array($data) && array_keys($data) === range(0, count($data) - 1)) {
            $this->addError($path, 'Must be an object, not an indexed array');
            return;
        }

        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (is_array($data) && ! array_key_exists($required, $data)) {
                    $this->addError($path ? "{$path}.{$required}" : $required, 'Required property is missing');
                } elseif (is_object($data) && ! property_exists($data, $required)) {
                    $this->addError($path ? "{$path}.{$required}" : $required, 'Required property is missing');
                }
            }
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            // 处理数组数据
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (isset($schema['properties'][$key])) {
                        $propertySchema = $schema['properties'][$key];
                        $propertyPath = $path ? "{$path}.{$key}" : $key;

                        if (isset($propertySchema['$ref'])) {
                            // 处理引用类型
                            // 这里简化处理，实际应该解析引用并获取对应的 schema
                            // 因为这超出了当前示例的范围，所以忽略引用的验证
                            continue;
                        }

                        $this->validateSchema($value, $propertySchema, $propertyPath);
                    } elseif (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                        $this->addError($path ? "{$path}.{$key}" : $key, 'Additional property not allowed');
                    }
                }
            }
            // 处理对象数据
            elseif (is_object($data)) {
                foreach (get_object_vars($data) as $key => $value) {
                    if (isset($schema['properties'][$key])) {
                        $propertySchema = $schema['properties'][$key];
                        $propertyPath = $path ? "{$path}.{$key}" : $key;

                        if (isset($propertySchema['$ref'])) {
                            continue;
                        }

                        $this->validateSchema($value, $propertySchema, $propertyPath);
                    } elseif (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                        $this->addError($path ? "{$path}.{$key}" : $key, 'Additional property not allowed');
                    }
                }
            }
        }
    }

    /**
     * 验证数组类型.
     * @param mixed $data
     */
    protected function validateArray($data, array $schema, ?string $path = null): void
    {
        if (! is_array($data) || array_keys($data) != range(0, count($data) - 1)) {
            $this->addError($path, 'Must be an array');
            return;
        }

        if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
            $this->addError($path, 'Array must have at least ' . $schema['minItems'] . ' items');
        }

        if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
            $this->addError($path, 'Array must have at most ' . $schema['maxItems'] . ' items');
        }

        if (isset($schema['uniqueItems']) && $schema['uniqueItems'] === true) {
            $values = [];
            foreach ($data as $index => $item) {
                $serialized = serialize($item);
                if (in_array($serialized, $values)) {
                    $this->addError($path, 'Array items must be unique');
                    break;
                }
                $values[] = $serialized;
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            foreach ($data as $index => $item) {
                $itemPath = $path ? "{$path}[{$index}]" : "[{$index}]";
                $this->validateSchema($item, $schema['items'], $itemPath);
            }
        }
    }

    /**
     * 验证字符串类型.
     * @param mixed $data
     */
    protected function validateString($data, array $schema, ?string $path = null): void
    {
        if (! is_string($data)) {
            $this->addError($path, 'Must be a string');
            return;
        }

        if (isset($schema['minLength']) && mb_strlen($data) < $schema['minLength']) {
            $this->addError($path, 'String must be at least ' . $schema['minLength'] . ' characters');
        }

        if (isset($schema['maxLength']) && mb_strlen($data) > $schema['maxLength']) {
            $this->addError($path, 'String must be at most ' . $schema['maxLength'] . ' characters');
        }

        if (isset($schema['pattern'])) {
            $pattern = '/' . $schema['pattern'] . '/';
            if (! preg_match($pattern, $data)) {
                $this->addError($path, 'String does not match pattern: ' . $schema['pattern']);
            }
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($data, $schema['enum'])) {
            $this->addError($path, 'Value must be one of: ' . implode(', ', $schema['enum']));
        }
    }

    /**
     * 验证数字类型.
     * @param mixed $data
     */
    protected function validateNumber($data, array $schema, ?string $path = null): void
    {
        // 首先检查是否是字符串类型的数字，并尝试转换
        if (is_string($data) && is_numeric($data)) {
            $data = strpos($data, '.') !== false ? (float) $data : (int) $data;
        }

        if (! is_int($data) && ! is_float($data)) {
            $this->addError($path, 'Must be a number');
            return;
        }

        if (isset($schema['minimum']) && $data < $schema['minimum']) {
            $this->addError($path, "Must be at least {$schema['minimum']}");
        }

        if (isset($schema['maximum']) && $data > $schema['maximum']) {
            $this->addError($path, "Must be at most {$schema['maximum']}");
        }

        if (isset($schema['exclusiveMinimum']) && $data <= $schema['exclusiveMinimum']) {
            $this->addError($path, "Must be greater than {$schema['exclusiveMinimum']}");
        }

        if (isset($schema['exclusiveMaximum']) && $data >= $schema['exclusiveMaximum']) {
            $this->addError($path, "Must be less than {$schema['exclusiveMaximum']}");
        }

        if (isset($schema['multipleOf']) && fmod($data, $schema['multipleOf']) !== 0.0) {
            $this->addError($path, "Must be a multiple of {$schema['multipleOf']}");
        }
    }

    /**
     * 验证整数类型.
     * @param mixed $data
     */
    protected function validateInteger($data, array $schema, ?string $path = null): void
    {
        // 首先检查是否是字符串类型的数字，并尝试转换
        if (is_string($data) && is_numeric($data) && strpos($data, '.') === false) {
            $data = (int) $data;
        }

        if (! is_int($data) && (! is_numeric($data) || floor((float) $data) != $data)) {
            $this->addError($path, 'Must be an integer');
            return;
        }

        $this->validateNumber($data, $schema, $path);
    }

    /**
     * 验证布尔类型.
     * @param mixed $data
     */
    protected function validateBoolean($data, array $schema, ?string $path = null): void
    {
        // 首先检查是否是字符串类型的布尔值，并尝试转换
        if (is_string($data)) {
            $lowerData = strtolower($data);
            if (in_array($lowerData, ['true', 'yes', 'y', '1', 'on'], true)) {
                $data = true;
            } elseif (in_array($lowerData, ['false', 'no', 'n', '0', 'off'], true)) {
                $data = false;
            }
        }

        if (! is_bool($data)) {
            $this->addError($path, 'Must be a boolean');
        }
    }

    /**
     * 验证空类型.
     * @param mixed $data
     */
    protected function validateNull($data, array $schema, ?string $path = null): void
    {
        if ($data !== null) {
            $this->addError($path, 'Must be null');
        }
    }

    /**
     * 添加错误信息.
     */
    protected function addError(?string $path, string $message): void
    {
        $this->errors[] = [
            'path' => $path ?? 'root',
            'message' => $message,
        ];
    }

    /**
     * 检查数据类型是否匹配指定的类型.
     *
     * @param mixed $data 要检查的数据
     * @param string $type 期望的类型（string, number, integer, boolean, array, object）
     * @return bool 是否匹配
     */
    private function checkType($data, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($data);
            case 'number':
                return is_numeric($data);
            case 'integer':
                return is_int($data) || (is_string($data) && ctype_digit($data));
            case 'boolean':
                return is_bool($data);
            case 'array':
                return is_array($data) && array_keys($data) === range(0, count($data) - 1);
            case 'object':
                return is_array($data) && ! empty($data) && array_keys($data) !== range(0, count($data) - 1);
            default:
                return false;
        }
    }
}

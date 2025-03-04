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
 * JSON Schema 构建器，用于创建符合 JSON Schema 标准的工具定义.
 */
class JsonSchemaBuilder
{
    /**
     * @var array 当前正在构建的 Schema
     */
    protected array $schema = [];

    /**
     * 构建一个新的 JSON Schema.
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * 重置构建器状态.
     */
    public function reset(): self
    {
        $this->schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
        return $this;
    }

    /**
     * 设置 Schema 的类型.
     */
    public function setType(string $type): self
    {
        $validTypes = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];
        if (! in_array($type, $validTypes)) {
            throw new InvalidArgumentException('Invalid schema type. Must be one of: ' . implode(', ', $validTypes));
        }
        $this->schema['type'] = $type;
        return $this;
    }

    /**
     * 添加一个字符串类型的属性.
     */
    public function addStringProperty(
        string $name,
        string $description,
        bool $required = false,
        ?int $minLength = null,
        ?int $maxLength = null,
        ?string $pattern = null,
        ?string $format = null,
        ?array $enum = null
    ): self {
        $property = [
            'type' => 'string',
            'description' => $description,
        ];

        if ($minLength !== null) {
            $property['minLength'] = $minLength;
        }

        if ($maxLength !== null) {
            $property['maxLength'] = $maxLength;
        }

        if ($pattern !== null) {
            $property['pattern'] = $pattern;
        }

        if ($format !== null) {
            $property['format'] = $format;
        }

        if ($enum !== null) {
            $property['enum'] = $enum;
        }

        $this->schema['properties'][$name] = $property;

        if ($required) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 添加一个数字类型的属性.
     */
    public function addNumberProperty(
        string $name,
        string $description,
        bool $required = false,
        bool $integer = false,
        ?float $minimum = null,
        ?float $maximum = null,
        ?bool $exclusiveMinimum = null,
        ?bool $exclusiveMaximum = null,
        ?float $multipleOf = null
    ): self {
        $property = [
            'type' => $integer ? 'integer' : 'number',
            'description' => $description,
        ];

        if ($minimum !== null) {
            $property['minimum'] = $minimum;
        }

        if ($maximum !== null) {
            $property['maximum'] = $maximum;
        }

        if ($exclusiveMinimum !== null) {
            $property['exclusiveMinimum'] = $exclusiveMinimum;
        }

        if ($exclusiveMaximum !== null) {
            $property['exclusiveMaximum'] = $exclusiveMaximum;
        }

        if ($multipleOf !== null) {
            $property['multipleOf'] = $multipleOf;
        }

        $this->schema['properties'][$name] = $property;

        if ($required) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 添加一个布尔类型的属性.
     */
    public function addBooleanProperty(
        string $name,
        string $description,
        bool $required = false
    ): self {
        $property = [
            'type' => 'boolean',
            'description' => $description,
        ];

        $this->schema['properties'][$name] = $property;

        if ($required) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 添加一个对象类型的属性.
     */
    public function addObjectProperty(
        string $name,
        string $description,
        array $properties = [],
        array $required = [],
        bool $isRequired = false
    ): self {
        $property = [
            'type' => 'object',
            'description' => $description,
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $property['required'] = $required;
        }

        $this->schema['properties'][$name] = $property;

        if ($isRequired) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 添加一个数组类型的属性.
     */
    public function addArrayProperty(
        string $name,
        string $description,
        array $items = [],
        bool $required = false,
        ?int $minItems = null,
        ?int $maxItems = null,
        ?bool $uniqueItems = null
    ): self {
        $property = [
            'type' => 'array',
            'description' => $description,
        ];

        if (! empty($items)) {
            $property['items'] = $items;
        }

        if ($minItems !== null) {
            $property['minItems'] = $minItems;
        }

        if ($maxItems !== null) {
            $property['maxItems'] = $maxItems;
        }

        if ($uniqueItems !== null) {
            $property['uniqueItems'] = $uniqueItems;
        }

        $this->schema['properties'][$name] = $property;

        if ($required) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 添加一个引用类型的属性.
     */
    public function addRefProperty(
        string $name,
        string $description,
        string $ref,
        bool $required = false
    ): self {
        $property = [
            'description' => $description,
            '$ref' => $ref,
        ];

        $this->schema['properties'][$name] = $property;

        if ($required) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * 获取构建好的 Schema.
     */
    public function build(): array
    {
        if (empty($this->schema['required'])) {
            unset($this->schema['required']);
        }

        return $this->schema;
    }
}

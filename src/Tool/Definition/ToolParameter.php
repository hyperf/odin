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

use Hyperf\Contract\Arrayable;
use InvalidArgumentException;

/**
 * JSON Schema 兼容的参数定义类.
 * 基于 JSON Schema Draft 7 规范设计.
 * @see https://json-schema.org/understanding-json-schema/
 */
class ToolParameter implements Arrayable
{
    /**
     * 参数名称.
     */
    protected string $name;

    /**
     * 参数描述.
     */
    protected string $description;

    /**
     * 参数类型.
     * @var array|string 可以是单一类型或类型数组
     */
    protected array|string $type;

    /**
     * 参数是否必需.
     */
    protected bool $required = false;

    /**
     * 枚举值列表.
     */
    protected ?array $enum = null;

    /**
     * 格式定义（如 date-time, email, uri 等）.
     */
    protected ?string $format = null;

    /**
     * 字符串类型的最小长度.
     */
    protected ?int $minLength = null;

    /**
     * 字符串类型的最大长度.
     */
    protected ?int $maxLength = null;

    /**
     * 字符串类型的正则表达式模式.
     */
    protected ?string $pattern = null;

    /**
     * 数值类型的最小值.
     */
    protected ?float $minimum = null;

    /**
     * 数值类型的最大值.
     */
    protected ?float $maximum = null;

    /**
     * 是否排除最小值.
     */
    protected ?bool $exclusiveMinimum = null;

    /**
     * 是否排除最大值.
     */
    protected ?bool $exclusiveMaximum = null;

    /**
     * 数值类型的倍数.
     */
    protected ?float $multipleOf = null;

    /**
     * 数组类型的最小数量.
     */
    protected ?int $minItems = null;

    /**
     * 数组类型的最大数量.
     */
    protected ?int $maxItems = null;

    /**
     * 数组元素是否唯一.
     */
    protected ?bool $uniqueItems = null;

    /**
     * 数组元素的类型定义.
     */
    protected ?array $items = null;

    /**
     * 对象类型的属性定义.
     * @var ToolParameter[]
     */
    protected array $properties = [];

    /**
     * 对象类型的必需属性列表.
     */
    protected array $propertyRequired = [];

    /**
     * 是否允许附加属性.
     */
    protected ?bool $additionalProperties = null;

    /**
     * 引用类型.
     */
    protected ?string $ref = null;

    /**
     * 元数据：标题.
     */
    protected ?string $title = null;

    /**
     * 元数据：示例.
     */
    protected ?array $examples = null;

    /**
     * 元数据：默认值.
     */
    protected mixed $default = null;

    /**
     * 自定义扩展属性.
     */
    protected array $extensions = [];

    /**
     * 构造函数.
     *
     * @param string $name 参数名称
     * @param string $description 参数描述
     * @param array|string $type 参数类型，支持 string、number、integer、boolean、array、object
     * @param bool $required 是否必需
     */
    public function __construct(
        string $name,
        string $description = '',
        array|string $type = 'string',
        bool $required = false
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->setType($type);
        $this->required = $required;
    }

    /**
     * 创建字符串类型参数.
     */
    public static function string(
        string $name,
        string $description = '',
        bool $required = false
    ): self {
        return new self($name, $description, 'string', $required);
    }

    /**
     * 创建数字类型参数.
     */
    public static function number(
        string $name,
        string $description = '',
        bool $required = false
    ): self {
        return new self($name, $description, 'number', $required);
    }

    /**
     * 创建整数类型参数.
     */
    public static function integer(
        string $name,
        string $description = '',
        bool $required = false
    ): self {
        return new self($name, $description, 'integer', $required);
    }

    /**
     * 创建布尔类型参数.
     */
    public static function boolean(
        string $name,
        string $description = '',
        bool $required = false
    ): self {
        return new self($name, $description, 'boolean', $required);
    }

    /**
     * 创建数组类型参数.
     */
    public static function array(
        string $name,
        string $description = '',
        ?array $items = null,
        bool $required = false
    ): self {
        $param = new self($name, $description, 'array', $required);
        if ($items !== null) {
            $param->setItems($items);
        }
        return $param;
    }

    /**
     * 创建对象类型参数.
     */
    public static function object(
        string $name,
        string $description = '',
        array $properties = [],
        array $required = [],
        bool $isRequired = false
    ): self {
        $param = new self($name, $description, 'object', $isRequired);
        foreach ($properties as $property) {
            if ($property instanceof self) {
                $param->addProperty($property);
                if (in_array($property->getName(), $required) || $property->isRequired()) {
                    $param->addPropertyRequired($property->getName());
                }
            }
        }
        return $param;
    }

    /**
     * 将参数转换为数组.
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        // 添加枚举值
        if ($this->enum !== null) {
            $result['enum'] = $this->enum;
        }

        // 添加格式定义
        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        // 添加字符串相关验证规则
        if ($this->type === 'string') {
            if ($this->minLength !== null) {
                $result['minLength'] = $this->minLength;
            }
            if ($this->maxLength !== null) {
                $result['maxLength'] = $this->maxLength;
            }
            if ($this->pattern !== null) {
                $result['pattern'] = $this->pattern;
            }
        }

        // 添加数值相关验证规则
        if ($this->type === 'number' || $this->type === 'integer') {
            if ($this->minimum !== null) {
                $result['minimum'] = $this->minimum;
            }
            if ($this->maximum !== null) {
                $result['maximum'] = $this->maximum;
            }
            if ($this->exclusiveMinimum !== null) {
                $result['exclusiveMinimum'] = $this->exclusiveMinimum;
            }
            if ($this->exclusiveMaximum !== null) {
                $result['exclusiveMaximum'] = $this->exclusiveMaximum;
            }
            if ($this->multipleOf !== null) {
                $result['multipleOf'] = $this->multipleOf;
            }
        }

        // 添加数组相关验证规则
        if ($this->type === 'array') {
            if ($this->minItems !== null) {
                $result['minItems'] = $this->minItems;
            }
            if ($this->maxItems !== null) {
                $result['maxItems'] = $this->maxItems;
            }
            if ($this->uniqueItems !== null) {
                $result['uniqueItems'] = $this->uniqueItems;
            }
            if ($this->items !== null) {
                $result['items'] = $this->items;
            }
        }

        // 添加对象相关验证规则
        if ($this->type === 'object') {
            $properties = [];
            foreach ($this->properties as $property) {
                $properties[$property->getName()] = $property->toArray();
            }
            if (! empty($properties)) {
                $result['properties'] = $properties;
            }
            if (! empty($this->propertyRequired)) {
                $result['required'] = $this->propertyRequired;
            }
            if ($this->additionalProperties !== null) {
                $result['additionalProperties'] = $this->additionalProperties;
            }
        }

        // 添加引用类型
        if ($this->ref !== null) {
            $result['$ref'] = $this->ref;
            // 引用类型不需要 type 属性
            unset($result['type']);
        }

        // 添加元数据
        if ($this->title !== null) {
            $result['title'] = $this->title;
        }
        if ($this->examples !== null) {
            $result['examples'] = $this->examples;
        }
        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        // 添加自定义扩展属性
        foreach ($this->extensions as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * 从数组创建参数定义.
     */
    public static function fromArray(string $name, array $schema): ?self
    {
        $type = $schema['type'] ?? 'string';
        $description = $schema['description'] ?? '';

        $parameter = new self($name, $description, $type, false);

        // 设置公共属性
        if (isset($schema['enum'])) {
            $parameter->setEnum($schema['enum']);
        }

        if (isset($schema['format'])) {
            $parameter->setFormat($schema['format']);
        }

        if (isset($schema['default'])) {
            $parameter->setDefault($schema['default']);
        }

        if (isset($schema['examples'])) {
            $parameter->setExamples($schema['examples']);
        }

        if (isset($schema['title'])) {
            $parameter->setTitle($schema['title']);
        }

        // 设置字符串相关属性
        if ($type === 'string') {
            if (isset($schema['minLength'])) {
                $parameter->setMinLength($schema['minLength']);
            }
            if (isset($schema['maxLength'])) {
                $parameter->setMaxLength($schema['maxLength']);
            }
            if (isset($schema['pattern'])) {
                $parameter->setPattern($schema['pattern']);
            }
        }

        // 设置数值相关属性
        if ($type === 'number' || $type === 'integer') {
            if (isset($schema['minimum'])) {
                $parameter->setMinimum($schema['minimum']);
            }
            if (isset($schema['maximum'])) {
                $parameter->setMaximum($schema['maximum']);
            }
            if (isset($schema['exclusiveMinimum'])) {
                $parameter->setExclusiveMinimum($schema['exclusiveMinimum']);
            }
            if (isset($schema['exclusiveMaximum'])) {
                $parameter->setExclusiveMaximum($schema['exclusiveMaximum']);
            }
            if (isset($schema['multipleOf'])) {
                $parameter->setMultipleOf($schema['multipleOf']);
            }
        }

        // 设置数组相关属性
        if ($type === 'array') {
            if (isset($schema['minItems'])) {
                $parameter->setMinItems($schema['minItems']);
            }
            if (isset($schema['maxItems'])) {
                $parameter->setMaxItems($schema['maxItems']);
            }
            if (isset($schema['uniqueItems'])) {
                $parameter->setUniqueItems($schema['uniqueItems']);
            }
            if (isset($schema['items'])) {
                $parameter->setItems($schema['items']);
            }
        }

        // 设置对象相关属性
        if ($type === 'object') {
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $propName => $propSchema) {
                    $property = self::fromArray($propName, $propSchema);
                    if (isset($schema['required']) && in_array($propName, $schema['required'])) {
                        $property->setRequired(true);
                    }
                    $parameter->addProperty($property);
                }
            } else {
                // 没有任何属性是 null
                return null;
            }
            if (isset($schema['additionalProperties'])) {
                $parameter->setAdditionalProperties($schema['additionalProperties']);
            }
        }

        // 设置引用类型
        if (isset($schema['$ref'])) {
            $parameter->setRef($schema['$ref']);
        }

        return $parameter;
    }

    // Getters and Setters

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): self
    {
        $validTypes = ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'];

        if (is_string($type)) {
            if (! in_array($type, $validTypes)) {
                throw new InvalidArgumentException('Invalid type. Must be one of: ' . implode(', ', $validTypes));
            }
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                if (! in_array($t, $validTypes)) {
                    throw new InvalidArgumentException('Invalid type. Must be one of: ' . implode(', ', $validTypes));
                }
            }
        } else {
            throw new InvalidArgumentException('Type must be a string or an array of strings');
        }

        $this->type = $type;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function setEnum(array $enum): self
    {
        $this->enum = $enum;
        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(int $minLength): self
    {
        $this->minLength = $minLength;
        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): self
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getMinimum(): ?float
    {
        return $this->minimum;
    }

    public function setMinimum(float $minimum): self
    {
        $this->minimum = $minimum;
        return $this;
    }

    public function getMaximum(): ?float
    {
        return $this->maximum;
    }

    public function setMaximum(float $maximum): self
    {
        $this->maximum = $maximum;
        return $this;
    }

    public function getExclusiveMinimum(): ?bool
    {
        return $this->exclusiveMinimum;
    }

    public function setExclusiveMinimum(bool $exclusiveMinimum): self
    {
        $this->exclusiveMinimum = $exclusiveMinimum;
        return $this;
    }

    public function getExclusiveMaximum(): ?bool
    {
        return $this->exclusiveMaximum;
    }

    public function setExclusiveMaximum(bool $exclusiveMaximum): self
    {
        $this->exclusiveMaximum = $exclusiveMaximum;
        return $this;
    }

    public function getMultipleOf(): ?float
    {
        return $this->multipleOf;
    }

    public function setMultipleOf(float $multipleOf): self
    {
        $this->multipleOf = $multipleOf;
        return $this;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function setMinItems(int $minItems): self
    {
        $this->minItems = $minItems;
        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setMaxItems(int $maxItems): self
    {
        $this->maxItems = $maxItems;
        return $this;
    }

    public function getUniqueItems(): ?bool
    {
        return $this->uniqueItems;
    }

    public function setUniqueItems(bool $uniqueItems): self
    {
        $this->uniqueItems = $uniqueItems;
        return $this;
    }

    public function getItems(): ?array
    {
        return $this->items;
    }

    public function setItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(ToolParameter $property): self
    {
        $this->properties[] = $property;
        if ($property->isRequired()) {
            $this->addPropertyRequired($property->getName());
        }
        return $this;
    }

    public function getPropertyRequired(): array
    {
        return $this->propertyRequired;
    }

    public function addPropertyRequired(string $name): self
    {
        if (! in_array($name, $this->propertyRequired)) {
            $this->propertyRequired[] = $name;
        }
        return $this;
    }

    public function getAdditionalProperties(): ?bool
    {
        return $this->additionalProperties;
    }

    public function setAdditionalProperties(bool $additionalProperties): self
    {
        $this->additionalProperties = $additionalProperties;
        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(string $ref): self
    {
        $this->ref = $ref;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getExamples(): ?array
    {
        return $this->examples;
    }

    public function setExamples(array $examples): self
    {
        $this->examples = $examples;
        return $this;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($default): self
    {
        $this->default = $default;
        return $this;
    }

    public function addExtension(string $key, $value): self
    {
        $this->extensions[$key] = $value;
        return $this;
    }

    public function getExtension(string $key)
    {
        return $this->extensions[$key] ?? null;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }
}

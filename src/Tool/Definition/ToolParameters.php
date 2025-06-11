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

/**
 * 参数集合类，用于表示一组参数定义.
 * 支持 JSON Schema 标准.
 */
class ToolParameters implements Arrayable
{
    /**
     * 参数类型.
     */
    protected string $type;

    /**
     * 参数属性列表.
     * @var ToolParameter[]
     */
    protected array $properties = [];

    /**
     * 必需的参数名称列表.
     */
    protected array $required = [];

    /**
     * 属性的附加属性.
     */
    protected ?bool $additionalProperties = null;

    /**
     * 元数据：标题.
     */
    protected ?string $title = null;

    /**
     * 元数据：描述.
     */
    protected ?string $description = null;

    /**
     * 构造函数.
     *
     * @param array $properties 参数属性列表
     * @param string $type 参数类型，默认为 object
     * @param null|string $title 标题
     * @param null|string $description 描述
     */
    public function __construct(
        array $properties = [],
        string $type = 'object',
        ?string $title = null,
        ?string $description = null
    ) {
        $this->properties = $properties;
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;

        // 设置必需参数
        foreach ($properties as $property) {
            if (! $property instanceof ToolParameter) {
                continue;
            }
            if ($property->isRequired()) {
                $this->required[] = $property->getName();
            }
        }
    }

    /**
     * 将参数转换为数组，兼容 JSON Schema 格式.
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->getType(),
        ];

        // 添加标题和描述
        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        // 处理属性
        $properties = [];
        foreach ($this->getProperties() as $property) {
            if (! $property instanceof ToolParameter) {
                continue;
            }
            $properties[$property->getName()] = $property->toArray();
        }

        if (! empty($properties)) {
            $result['properties'] = $properties;
        }

        // 添加必需属性
        if (! empty($this->required)) {
            $result['required'] = $this->getRequired();
        }

        // 添加附加属性
        if ($this->additionalProperties !== null) {
            $result['additionalProperties'] = $this->additionalProperties;
        }

        return $result;
    }

    /**
     * 从数组创建参数集合.
     *
     * @param array $parameters 参数数组
     */
    public static function fromArray(array $parameters): self
    {
        $type = $parameters['type'] ?? 'object';
        $title = $parameters['title'] ?? null;
        $description = $parameters['description'] ?? null;

        $toolParameters = new self([], $type, $title, $description);

        // 设置附加属性
        if (isset($parameters['additionalProperties'])) {
            $toolParameters->setAdditionalProperties($parameters['additionalProperties']);
        }

        // 处理属性
        if (isset($parameters['properties']) && is_array($parameters['properties'])) {
            $properties = [];
            $required = $parameters['required'] ?? [];

            foreach ($parameters['properties'] as $name => $property) {
                // 从属性定义创建 ToolParameter 对象
                $param = ToolParameter::fromArray($name, $property);
                if ($param) {
                    // 设置必需属性
                    if (in_array($name, $required)) {
                        $param->setRequired(true);
                    }
                    $properties[] = $param;
                }
            }

            $toolParameters->setProperties($properties);
        }

        return $toolParameters;
    }

    /**
     * 获取参数类型.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 设置参数类型.
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 获取参数属性列表.
     *
     * @return ToolParameter[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * 设置参数属性列表.
     *
     * @param ToolParameter[] $properties
     */
    public function setProperties(array $properties): self
    {
        $this->properties = $properties;

        // 更新必需参数列表
        $this->required = [];
        foreach ($properties as $property) {
            if ($property instanceof ToolParameter && $property->isRequired()) {
                $this->required[] = $property->getName();
            }
        }

        return $this;
    }

    /**
     * 添加参数属性.
     */
    public function addProperty(ToolParameter $property): self
    {
        $this->properties[] = $property;

        // 如果参数是必需的，更新必需参数列表
        if ($property->isRequired()) {
            $this->required[] = $property->getName();
        }

        return $this;
    }

    /**
     * 获取必需参数列表.
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * 设置必需参数列表.
     */
    public function setRequired(array $required): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * 获取附加属性.
     */
    public function getAdditionalProperties(): ?bool
    {
        return $this->additionalProperties;
    }

    /**
     * 设置附加属性.
     */
    public function setAdditionalProperties(bool $additionalProperties): self
    {
        $this->additionalProperties = $additionalProperties;
        return $this;
    }

    /**
     * 获取标题.
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * 设置标题.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * 获取描述.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * 设置描述.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}

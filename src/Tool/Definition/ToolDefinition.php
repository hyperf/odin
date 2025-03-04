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

use Closure;
use Hyperf\Contract\Arrayable;
use Hyperf\Odin\Tool\Definition\Schema\JsonSchemaValidator;
use InvalidArgumentException;

/**
 * 工具定义类，用于定义符合 JSON Schema 标准的工具.
 */
class ToolDefinition implements Arrayable
{
    /**
     * 工具名称.
     */
    protected string $name;

    /**
     * 工具描述.
     */
    protected string $description;

    /**
     * 工具参数定义.
     */
    protected ?ToolParameters $parameters;

    /**
     * 工具处理器.
     * @var callable[]
     */
    protected array|Closure $toolHandler = [];

    /**
     * 构造函数.
     *
     * @param string $name 工具名称
     * @param string $description 工具描述
     * @param null|ToolParameters $parameters 工具参数
     * @param array|callable|Closure $toolHandler 工具处理器
     */
    public function __construct(
        string $name,
        string $description = '',
        ?ToolParameters $parameters = null,
        array|callable|Closure $toolHandler = [],
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->setToolHandler($toolHandler);
    }

    /**
     * 将工具定义转换为数组.
     */
    public function toArray(): array
    {
        $result = [
            'type' => 'function',
            'function' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
            ],
        ];

        // 添加参数定义
        if ($this->getParameters() !== null) {
            $result['function']['parameters'] = $this->getParameters()->toArray();
        } else {
            // 没有参数时提供默认的空对象
            $result['function']['parameters'] = [
                'type' => 'object',
                'properties' => [],
            ];
        }

        return $result;
    }

    /**
     * 将工具定义转换为 JSON Schema 格式.
     */
    public function toJsonSchema(): array
    {
        // 设置基本信息
        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => $this->getName(),
            'description' => $this->getDescription(),
        ];

        // 合并参数定义
        if ($this->getParameters() !== null) {
            $parametersSchema = $this->getParameters()->toArray();
            $schema = array_merge($schema, $parametersSchema);
        } else {
            // 默认为空对象
            $schema['type'] = 'object';
            $schema['properties'] = [];
        }

        return $schema;
    }

    /**
     * 验证参数是否符合工具定义.
     *
     * @param array $parameters 要验证的参数
     * @return array 验证结果，包含是否通过验证和错误信息
     */
    public function validateParameters(array $parameters): array
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->toJsonSchema();

        $isValid = $validator->validate($parameters, $schema);

        return [
            'valid' => $isValid,
            'errors' => $isValid ? [] : $validator->getErrors(),
        ];
    }

    /**
     * 获取工具处理器.
     */
    public function getToolHandler(): array|callable|Closure
    {
        return $this->toolHandler;
    }

    /**
     * 设置工具处理器.
     */
    public function setToolHandler(array|callable|Closure $toolHandler): self
    {
        if (! is_callable($toolHandler)) {
            throw new InvalidArgumentException('Tool handler must be callable.');
        }
        $this->toolHandler = $toolHandler;
        return $this;
    }

    /**
     * 获取工具名称.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 设置工具名称.
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 获取工具描述.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * 设置工具描述.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 获取工具参数.
     */
    public function getParameters(): ?ToolParameters
    {
        return $this->parameters;
    }

    /**
     * 设置工具参数.
     */
    public function setParameters(ToolParameters $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * 从 JSON Schema 数组创建工具参数.
     */
    public function setParametersFromSchema(array $schema): self
    {
        $this->parameters = ToolParameters::fromArray($schema);
        return $this;
    }
}

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

namespace Hyperf\Odin\Tool;

use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Exception\ToolParameterValidationException;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

/**
 * 工具抽象基类.
 */
abstract class AbstractTool implements ToolInterface
{
    protected string $name = '';

    protected string $description = '';

    protected ?ToolParameters $parameters = null;

    /**
     * 是否启用参数验证.
     */
    protected bool $validateParameters = true;

    /**
     * 是否启用参数自动转换.
     */
    protected bool $convertParameters = true;

    /**
     * 获取工具定义.
     */
    public function toToolDefinition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->getName(),
            description: $this->getDescription(),
            parameters: $this->getParameters(),
            toolHandler: [$this, 'run'],
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getParameters(): ?ToolParameters
    {
        return $this->parameters;
    }

    public function setParameters(?ToolParameters $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * 运行工具.
     *
     * @param array $parameters 工具参数
     * @return mixed 工具执行结果
     * @throws ToolParameterValidationException 当参数验证失败时抛出异常
     */
    public function run(array $parameters): array
    {
        // 获取工具定义
        $definition = $this->toToolDefinition();

        // 如果有参数
        if (! empty($definition->getParameters()?->getProperties())) {
            // 如果启用了参数自动转换，先转换参数
            if ($this->convertParameters) {
                $parameters = $this->convertParameters($parameters, $definition);
            }

            // 如果启用了参数验证，则在执行前验证参数
            if ($this->validateParameters) {
                $this->validateParameters($parameters, $definition);
            }
        }

        // 执行工具逻辑
        return $this->handle($parameters);
    }

    /**
     * 设置是否启用参数验证.
     *
     * @param bool $validate 是否验证
     */
    public function setValidateParameters(bool $validate): self
    {
        $this->validateParameters = $validate;
        return $this;
    }

    /**
     * 是否启用参数验证.
     */
    public function isValidateParameters(): bool
    {
        return $this->validateParameters;
    }

    /**
     * 设置是否启用参数自动转换.
     *
     * @param bool $convert 是否自动转换
     */
    public function setConvertParameters(bool $convert): self
    {
        $this->convertParameters = $convert;
        return $this;
    }

    /**
     * 是否启用参数自动转换.
     */
    public function isConvertParameters(): bool
    {
        return $this->convertParameters;
    }

    /**
     * 处理工具逻辑.
     *
     * @param array $parameters 工具参数。
     *                          当启用了参数自动转换功能（默认开启）时，符合以下条件的参数将被自动转换类型：
     *                          - 字符串类型的数字会被转换为整数或浮点数
     *                          - 字符串表示的布尔值(true/false, yes/no, 1/0)会被转换为布尔类型
     * @return mixed 工具执行结果
     */
    abstract protected function handle(array $parameters): array;

    /**
     * 转换参数类型，使其符合工具定义.
     *
     * @param array $parameters 原始参数
     * @param ToolDefinition $definition 工具定义
     * @return array 转换后的参数
     */
    protected function convertParameters(array $parameters, ToolDefinition $definition): array
    {
        $toolParameters = $this->getParameters();
        if ($toolParameters === null) {
            return $parameters;
        }

        $schema = $toolParameters->toArray();

        // 如果没有定义属性，无需转换
        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return $parameters;
        }

        // 遍历所有定义的属性
        foreach ($schema['properties'] as $propName => $propSchema) {
            // 如果参数中有这个属性
            if (array_key_exists($propName, $parameters)) {
                // 获取属性类型
                $type = $propSchema['type'] ?? 'string';

                // 根据类型进行强制类型转换
                if ($type === 'integer' && is_string($parameters[$propName]) && is_numeric($parameters[$propName])) {
                    $parameters[$propName] = (int) $parameters[$propName];
                    continue;
                }

                if ($type === 'number' && is_string($parameters[$propName]) && is_numeric($parameters[$propName])) {
                    $parameters[$propName] = (float) $parameters[$propName];
                    continue;
                }

                if ($type === 'boolean' && is_string($parameters[$propName])) {
                    $value = strtolower($parameters[$propName]);
                    if ($value === 'true' || $value === '1' || $value === 'yes') {
                        $parameters[$propName] = true;
                        continue;
                    }
                    if ($value === 'false' || $value === '0' || $value === 'no' || $value === '') {
                        $parameters[$propName] = false;
                        continue;
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * 验证参数是否符合工具定义.
     *
     * @param array $parameters 要验证的参数
     * @param ToolDefinition $definition 工具定义
     * @throws ToolParameterValidationException 当验证失败时抛出异常
     */
    protected function validateParameters(array $parameters, ToolDefinition $definition): void
    {
        // 验证参数
        $validationResult = $definition->validateParameters($parameters);

        // 如果验证失败，抛出异常
        if (! $validationResult['valid']) {
            throw new ToolParameterValidationException(
                '工具参数验证失败：' . $this->formatValidationErrors($validationResult['errors']),
                $validationResult['errors']
            );
        }
    }

    /**
     * 格式化验证错误信息.
     *
     * @param array $errors 错误信息数组
     * @return string 格式化后的错误信息
     */
    protected function formatValidationErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $error) {
            $messages[] = "{$error['message']}";
        }

        return implode('; ', $messages);
    }
}

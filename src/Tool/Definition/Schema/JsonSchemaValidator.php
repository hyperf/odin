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

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * JSON Schema 验证器，用于验证数据是否符合 JSON Schema 规范.
 * 使用 jsonrainbow/json-schema 组件实现.
 *
 * @see SchemaValidator 如需验证Schema本身是否有效
 */
class JsonSchemaValidator
{
    /**
     * @var array 验证错误信息
     */
    protected array $errors = [];

    /**
     * @var Validator JSON Schema 验证器实例
     */
    protected Validator $validator;

    /**
     * 构造函数.
     */
    public function __construct()
    {
        $this->validator = new Validator();
    }

    /**
     * 验证数据是否符合指定的 Schema.
     *
     * @param array $data 要验证的数据
     * @param array $schema Schema定义
     * @param int $checkMode 验证模式标志，可使用Constraint::CHECK_MODE_*常量
     * @return bool 验证是否通过
     */
    public function validate(array $data, array $schema, int $checkMode = Constraint::CHECK_MODE_NORMAL): bool
    {
        $this->errors = [];
        $this->validator->reset();

        // 将数组转换为对象，因为jsonrainbow/json-schema需要对象格式的schema和数据
        $schemaObject = json_decode(json_encode($schema));
        $dataObject = json_decode(json_encode($data));

        $this->validator->validate($dataObject, $schemaObject, $checkMode);
        $this->errors = $this->validator->getErrors();

        return $this->validator->isValid();
    }

    /**
     * 获取验证错误信息.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

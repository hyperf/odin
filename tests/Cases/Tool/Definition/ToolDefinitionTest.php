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

namespace HyperfTest\Odin\Cases\Tool\Definition;

use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameter;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class ToolDefinitionTest extends ToolBaseTestCase
{
    /**
     * 测试创建有效工具定义.
     */
    public function testCreateValidToolDefinition(): void
    {
        // 创建基本工具定义
        $definition = new ToolDefinition(
            'test_tool',
            '测试工具',
            null,
            function ($params) {
                return ['result' => true];
            }
        );

        $this->assertEquals('test_tool', $definition->getName());
        $this->assertEquals('测试工具', $definition->getDescription());
        $this->assertNull($definition->getParameters());
        $this->assertIsCallable($definition->getToolHandler());

        // 使用参数创建
        $parameters = new ToolParameters(
            [
                ToolParameter::string('name', '名称', true),
                ToolParameter::integer('age', '年龄'),
            ]
        );

        $definition = new ToolDefinition(
            'advanced_tool',
            '高级测试工具',
            $parameters,
            function ($params) {
                return ['result' => true];
            }
        );

        $this->assertEquals('advanced_tool', $definition->getName());
        $this->assertEquals('高级测试工具', $definition->getDescription());
        $this->assertSame($parameters, $definition->getParameters());
        $this->assertIsCallable($definition->getToolHandler());
    }

    /**
     * 测试 toArray 方法.
     */
    public function testToArrayMethod(): void
    {
        $definition = $this->createSimpleToolDefinition();
        $array = $definition->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('function', $array['type']);
        $this->assertArrayHasKey('function', $array);
        $this->assertEquals('test_tool', $array['function']['name']);
        $this->assertEquals('测试工具', $array['function']['description']);
        $this->assertArrayHasKey('parameters', $array['function']);
        $this->assertIsArray($array['function']['parameters']);
        $this->assertArrayHasKey('type', $array['function']['parameters']);
        $this->assertEquals('object', $array['function']['parameters']['type']);
        $this->assertArrayHasKey('properties', $array['function']['parameters']);
        $this->assertIsArray($array['function']['parameters']['properties']);
        $this->assertArrayHasKey('name', $array['function']['parameters']['properties']);
        $this->assertArrayHasKey('age', $array['function']['parameters']['properties']);
    }

    /**
     * 测试无参数的 toArray 方法.
     */
    public function testToArrayWithoutParameters(): void
    {
        $definition = new ToolDefinition(
            'no_param_tool',
            '无参数工具',
            null,
            function () {
                return ['result' => 'ok'];
            }
        );

        $array = $definition->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('function', $array['type']);
        $this->assertArrayHasKey('function', $array);
        $this->assertEquals('no_param_tool', $array['function']['name']);
        $this->assertEquals('无参数工具', $array['function']['description']);
        $this->assertArrayHasKey('parameters', $array['function']);
        $this->assertEquals('object', $array['function']['parameters']['type']);
        $this->assertArrayHasKey('properties', $array['function']['parameters']);
        $this->assertEmpty($array['function']['parameters']['properties']);
    }

    /**
     * 测试 toJsonSchema 方法.
     */
    public function testToJsonSchema(): void
    {
        $definition = $this->createSimpleToolDefinition();
        $jsonSchema = $definition->toJsonSchema();

        $this->assertIsArray($jsonSchema);
        $this->assertEquals('object', $jsonSchema['type']);
        $this->assertArrayHasKey('properties', $jsonSchema);
        $this->assertArrayHasKey('name', $jsonSchema['properties']);
        $this->assertArrayHasKey('age', $jsonSchema['properties']);
        $this->assertEquals('string', $jsonSchema['properties']['name']['type']);
        $this->assertEquals('integer', $jsonSchema['properties']['age']['type']);
        $this->assertEquals(['name'], $jsonSchema['required']);
    }

    /**
     * 测试参数验证功能（成功）.
     */
    public function testValidateParametersSuccess(): void
    {
        $definition = $this->createSimpleToolDefinition();

        $validParams = [
            'name' => '张三',
            'age' => 30,
        ];

        $result = $definition->validateParameters($validParams);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试参数验证功能（失败：缺少必填字段）.
     */
    public function testValidateParametersMissingRequired(): void
    {
        $definition = $this->createSimpleToolDefinition();

        $invalidParams = [
            'age' => 30,
            // 缺少 name 参数
        ];

        $result = $definition->validateParameters($invalidParams);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // 验证错误信息中包含缺少的字段信息
        $errorFound = false;
        foreach ($result['errors'] as $error) {
            if (isset($error['path'], $error['message'])
                && (strpos($error['path'], 'name') !== false
                || strpos($error['message'], 'name') !== false
                || strpos($error['message'], 'Required') !== false)) {
                $errorFound = true;
                break;
            }
        }
        $this->assertTrue($errorFound, '错误信息应该包含缺少的必填字段name');
    }

    /**
     * 测试参数验证功能（失败：类型错误）.
     */
    public function testValidateParametersTypeError(): void
    {
        $definition = $this->createSimpleToolDefinition();

        $invalidParams = [
            'name' => '李四',
            'age' => '三十岁', // 不是整数类型
        ];

        $result = $definition->validateParameters($invalidParams);

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);

        // 验证错误信息中包含类型错误信息
        $errorFound = false;
        foreach ($result['errors'] as $error) {
            if (isset($error['path'], $error['message'])
                && (strpos($error['path'], 'age') !== false
                || (strpos($error['message'], 'integer') !== false && strpos($error['path'], 'age') !== false))) {
                $errorFound = true;
                break;
            }
        }
        $this->assertTrue($errorFound, '错误信息应该包含类型错误信息');
    }

    /**
     * 测试参数验证功能（失败：值范围错误）.
     */
    public function testValidateParametersValueError(): void
    {
        $definition = new ToolDefinition(
            'age_tool',
            '年龄工具',
            new ToolParameters([
                ToolParameter::integer('age', '年龄')->setMinimum(18)->setMaximum(100),
            ]),
            function ($params) {
                return ['result' => true];
            }
        );

        // 值过小
        $validationResult = $definition->validateParameters(['age' => 10]);
        $this->assertFalse($validationResult['valid']);
        $this->assertNotEmpty($validationResult['errors']);
        $this->assertStringContainsString('Must be at least', $validationResult['errors'][0]['message']);

        // 值过大
        $validationResult = $definition->validateParameters(['age' => 150]);
        $this->assertFalse($validationResult['valid']);
        $this->assertNotEmpty($validationResult['errors']);
        $this->assertStringContainsString('Must be at most', $validationResult['errors'][0]['message']);
    }

    /**
     * 测试 setter 和 getter 方法.
     */
    public function testSetterAndGetterMethods(): void
    {
        $definition = new ToolDefinition('test_tool', '测试工具', null, function () {});

        // 测试名称属性
        $this->assertEquals('test_tool', $definition->getName());
        $definition->setName('new_name');
        $this->assertEquals('new_name', $definition->getName());

        // 测试描述属性
        $this->assertEquals('测试工具', $definition->getDescription());
        $definition->setDescription('新描述');
        $this->assertEquals('新描述', $definition->getDescription());

        // 测试参数属性
        $this->assertNull($definition->getParameters());
        $parameters = new ToolParameters(
            [
                ToolParameter::string('query', '查询条件', true),
                ToolParameter::integer('limit', '限制数量'),
            ]
        );
        $definition->setParameters($parameters);
        $this->assertSame($parameters, $definition->getParameters());

        // 测试 toolHandler 属性
        $this->assertIsCallable($definition->getToolHandler());
        $newHandler = function ($params) {
            return ['status' => 'changed'];
        };
        $definition->setToolHandler($newHandler);
        $this->assertSame($newHandler, $definition->getToolHandler());
    }

    /**
     * 测试从 JSON Schema 创建参数.
     */
    public function testSetParametersFromSchema(): void
    {
        $definition = new ToolDefinition(
            'schema_tool',
            'Schema工具',
            null,
            function ($params) {
                return ['result' => true];
            }
        );

        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '姓名',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                    'minimum' => 0,
                    'maximum' => 120,
                ],
                'email' => [
                    'type' => 'string',
                    'description' => '电子邮件',
                    'format' => 'email',
                ],
            ],
            'required' => ['name', 'email'],
        ];

        $definition->setParametersFromSchema($schema);
        $parameters = $definition->getParameters();

        $this->assertInstanceOf(ToolParameters::class, $parameters);
        $array = $parameters->toArray();
        $this->assertEquals('object', $array['type']);
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertArrayHasKey('email', $array['properties']);
        $this->assertEquals(['name', 'email'], $array['required']);
    }

    /**
     * 创建一个用于测试的简单工具定义.
     */
    protected function createSimpleToolDefinition(string $name = 'test_tool', string $description = '测试工具'): ToolDefinition
    {
        return new ToolDefinition(
            name: $name,
            description: $description,
            parameters: new ToolParameters([
                ToolParameter::string('name', '名称', true),
                ToolParameter::integer('age', '年龄'),
            ]),
            toolHandler: function ($params) {
                return ['result' => true];
            }
        );
    }

    /**
     * 创建一个复杂的工具定义（包含嵌套结构和多种参数类型）.
     */
    protected function createComplexToolDefinition(string $name = 'complex_tool', string $description = '复杂测试工具'): ToolDefinition
    {
        // 创建用户参数
        $userParams = [
            ToolParameter::string('name', '用户名称', true),
            ToolParameter::integer('age', '用户年龄')->setMinimum(0)->setMaximum(150),
            ToolParameter::string('email', '电子邮件', true)->setFormat('email'),
        ];

        // 创建设置参数
        $settingsParams = [
            ToolParameter::string('theme', '主题')->setEnum(['light', 'dark', 'auto']),
            ToolParameter::boolean('notifications', '是否开启通知'),
            ToolParameter::string('displayMode', '显示模式')->setEnum(['simple', 'advanced']),
        ];

        // 创建主要参数
        $mainParams = [
            ToolParameter::object('user', '用户信息', $userParams, ['name', 'email'], true),
            ToolParameter::array('preferences', '偏好设置', ['type' => 'string']),
            ToolParameter::object('settings', '设置信息', $settingsParams),
        ];

        return new ToolDefinition(
            name: $name,
            description: $description,
            parameters: new ToolParameters($mainParams),
            toolHandler: function ($params) {
                return ['result' => 'complex_success'];
            }
        );
    }
}

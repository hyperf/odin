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

namespace HyperfTest\Odin\Cases\Tool;

use Hyperf\Odin\Exception\ToolParameterValidationException;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use InvalidArgumentException;
use ReflectionClass;

/**
 * @internal
 * @coversNothing
 */
class AbstractToolTest extends ToolBaseTestCase
{
    /**
     * 测试工具定义获取.
     */
    public function testGetDefinition(): void
    {
        $tool = new class extends AbstractTool {
            protected string $name = 'simple_tool';

            protected string $description = '简单工具';

            protected function handle(array $parameters): array
            {
                return $parameters;
            }
        };
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        $definition = $tool->toToolDefinition();

        $this->assertInstanceOf(ToolDefinition::class, $definition);
        $this->assertEquals('simple_tool', $definition->getName());
        $this->assertEquals('简单工具', $definition->getDescription());
    }

    public function testEmpty()
    {
        $currentTimeTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 这个工具不需要任何参数，直接返回当前时间信息
                return [
                    'current_time' => date('Y-m-d H:i:s'),
                    'timezone' => date_default_timezone_get(),
                    'timestamp' => time(),
                ];
            }
        };
        $currentTimeTool->setName('get_current_time');
        $currentTimeTool->setDescription('获取当前系统时间，不需要任何参数');
        $currentTimeTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ]));

        $currentTimeTool->run([]);
        $this->assertTrue(true);
    }

    /**
     * 测试工具调用验证逻辑.
     */
    public function testValidateParameters(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                if ($name === '') {
                    throw new ToolParameterValidationException('工具参数验证失败: name 不能为空', ['name' => ['不能为空']]);
                }

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        // 有效参数测试 - 包括空字符串
        $validParams = ['name' => '测试', 'age' => 25];
        $result = $tool->run($validParams);
        $this->assertIsArray($result);

        // 无效参数测试 - 缺少必填项
        try {
            $invalidParams = ['age' => 25]; // 缺少 name
            $tool->run($invalidParams);
            $this->fail('缺少必填参数应该抛出异常');
        } catch (ToolParameterValidationException $e) {
            $this->assertStringContainsString('工具参数验证失败', $e->getMessage());
            $this->assertStringContainsString('name', $e->getMessage());
        }
    }

    /**
     * 测试参数类型自动转换.
     */
    public function testAutoConvertParameters(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                if ($name === '') {
                    throw new ToolParameterValidationException('工具参数验证失败: name 不能为空', ['name' => ['不能为空']]);
                }

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        // 字符串数字转为整数
        $params = ['name' => '测试', 'age' => '25'];
        $result = $tool->run($params);

        $this->assertEquals(26, $result['nextYearAge']);
    }

    /**
     * 测试工具执行流程.
     */
    public function testInvokeProcess(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                if ($name === '') {
                    throw new ToolParameterValidationException('工具参数验证失败: name 不能为空', ['name' => ['不能为空']]);
                }

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        $result = $tool->run(['name' => '张三', 'age' => 30]);

        $this->assertIsArray($result);
        $this->assertEquals('张三', $result['processedName']);
        $this->assertEquals(31, $result['nextYearAge']);

        // 测试参数转换
        $result = $tool->run(['name' => '李四', 'age' => '40']);

        $this->assertIsArray($result);
        $this->assertEquals('李四', $result['processedName']);
        $this->assertEquals(41, $result['nextYearAge']);
    }

    /**
     * 测试错误处理和格式化.
     */
    public function testErrorHandling(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                if ($name === '') {
                    throw new ToolParameterValidationException('工具参数验证失败: name 不能为空', ['name' => ['不能为空']]);
                }

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };

        // 测试参数验证异常格式化
        try {
            $tool->run(['age' => 30]); // 缺少 name
            $this->fail('应该抛出异常');
        } catch (ToolParameterValidationException $e) {
            $this->assertStringContainsString('工具参数验证失败', $e->getMessage());
            $this->assertStringContainsString('name', $e->getMessage());
            $this->assertNotEmpty($e->getValidationErrors(), '验证错误应该不为空');
            $this->assertIsArray($e->getValidationErrors());
        }

        // 测试业务逻辑中的异常
        try {
            // 创建一个会在处理时抛出异常的测试工具
            $exceptionTool = new class extends AbstractTool {
                protected function handle(array $parameters): array
                {
                    if ($parameters['trigger'] ?? false) {
                        throw new InvalidArgumentException('业务处理异常');
                    }
                    return ['success' => true];
                }
            };
            $exceptionTool->setName('exception_tool');
            $exceptionTool->setDescription('异常测试工具');
            $exceptionTool->setParameters(ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'trigger' => [
                        'type' => 'boolean',
                        'description' => '是否触发异常',
                    ],
                ],
            ]));

            $exceptionTool->run(['trigger' => true]);
            $this->fail('应该抛出异常');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('业务处理异常', $e->getMessage());
        }
    }

    /**
     * 测试参数验证开关.
     */
    public function testValidateParametersSwitch(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        // 默认开启参数验证
        $this->assertTrue($tool->isValidateParameters());

        // 有效参数测试
        $result = $tool->run(['name' => '张三', 'age' => 30]);
        $this->assertEquals('张三', $result['processedName']);
        $this->assertEquals(31, $result['nextYearAge']);

        // 关闭参数验证
        $tool->setValidateParameters(false);
        $this->assertFalse($tool->isValidateParameters());

        // 关闭验证后，不再验证参数完整性
        $result = $tool->run(['age' => 30]); // 缺少 name 但不会抛出异常
        $this->assertIsArray($result);
        $this->assertEquals(31, $result['nextYearAge']);
        $this->assertEquals('', $result['processedName']); // name 为空字符串

        // 即使没有任何参数也可以执行
        $result = $tool->run([]);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['nextYearAge']); // 默认 age 为 0，加 1 后为 1
        $this->assertEquals('', $result['processedName']); // name 为空字符串

        // 重新开启验证
        $tool->setValidateParameters(true);
        $this->assertTrue($tool->isValidateParameters());

        // 有效参数测试
        $result = $tool->run(['name' => '李四', 'age' => 40]);
        $this->assertEquals('李四', $result['processedName']);
        $this->assertEquals(41, $result['nextYearAge']);
    }

    /**
     * 测试参数自动转换开关.
     */
    public function testConvertParametersSwitch(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                    'ageType' => gettype($age),
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        // 先关闭参数验证，否则无法测试转换功能
        $tool->setValidateParameters(false);

        // 默认开启参数转换
        $this->assertTrue($tool->isConvertParameters());

        // 测试参数转换功能
        $result = $tool->run(['name' => '王五', 'age' => '50']);
        $this->assertEquals(51, $result['nextYearAge']); // 已转换为整数
        $this->assertEquals('integer', $result['ageType']); // 应该被转换为整数类型

        // 关闭参数转换
        $tool->setConvertParameters(false);
        $this->assertFalse($tool->isConvertParameters());

        // 使用整数参数，应该正常工作
        $result = $tool->run(['name' => '赵六', 'age' => 60]);
        $this->assertEquals(61, $result['nextYearAge']);
        $this->assertEquals('integer', $result['ageType']); // 类型是整数，因为直接传入的是整数

        // 重新开启转换
        $tool->setConvertParameters(true);
        $this->assertTrue($tool->isConvertParameters());

        // 恢复转换后应该可以正常处理字符串类型的数字
        $result = $tool->run(['name' => '钱七', 'age' => '70']);
        $this->assertEquals(71, $result['nextYearAge']); // 已转换为整数
        $this->assertEquals('integer', $result['ageType']); // 应该被转换为整数类型

        // 最后重新开启验证
        $tool->setValidateParameters(true);
    }

    /**
     * 测试子类实现的必要方法.
     */
    public function testRequiredMethodsImplementation(): void
    {
        // 使用反射检查 AbstractTool 中的抽象方法
        $reflectionClass = new ReflectionClass(AbstractTool::class);
        $this->assertTrue($reflectionClass->isAbstract(), 'AbstractTool 应该是抽象类');

        // 检查是否有 handle 抽象方法
        $handleMethod = $reflectionClass->getMethod('handle');
        $this->assertTrue($handleMethod->isAbstract(), 'handle 方法应该是抽象的');
        $this->assertTrue($handleMethod->isProtected(), 'handle 方法应该是 protected 的');

        // 验证返回类型
        $returnType = $handleMethod->getReturnType();
        $this->assertNotNull($returnType, 'handle 方法应该有返回类型');
        $this->assertEquals('array', $returnType->getName(), 'handle 方法应该返回 array 类型');

        // 验证参数
        $parameters = $handleMethod->getParameters();
        $this->assertCount(1, $parameters, 'handle 方法应该有一个参数');
        $this->assertEquals('parameters', $parameters[0]->getName(), '参数名应该是 parameters');
        $this->assertEquals('array', $parameters[0]->getType()->getName(), '参数类型应该是 array');
    }
}

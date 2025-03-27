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

namespace HyperfTest\Odin\Cases\Agent\Tool;

use Exception;
use Hyperf\Odin\Agent\Tool\MultiToolUseParallelTool;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * 测试 MultiToolUseParallelTool 类的功能.
 * @internal
 * @coversNothing
 */
class MultiToolUseParallelToolTest extends AbstractTestCase
{
    /**
     * 测试 __construct 方法能正确初始化对象和继承父类属性.
     */
    public function testConstructor()
    {
        // 创建测试用的工具定义
        $tool1 = new ToolDefinition(
            name: 'test_tool_1',
            description: '测试工具1',
            toolHandler: function ($params) {
                return 'tool1 result';
            }
        );

        $tool2 = new ToolDefinition(
            name: 'test_tool_2',
            description: '测试工具2',
            toolHandler: function ($params) {
                return 'tool2 result';
            }
        );

        // 创建工具集合
        $allTools = [
            'test_tool_1' => $tool1,
            'test_tool_2' => $tool2,
        ];

        // 创建 MultiToolUseParallelTool 实例
        $multiTool = new MultiToolUseParallelTool($allTools);

        // 验证实例是 ToolDefinition 的子类
        $this->assertInstanceOf(ToolDefinition::class, $multiTool);

        // 验证构造函数参数正确传递
        $this->assertEquals('multi_tool_use.parallel', $multiTool->getName());

        // 验证 allTools 属性正确设置
        $toolsProperty = $this->getNonpublicProperty($multiTool, 'allTools');
        $this->assertCount(2, $toolsProperty);
        $this->assertSame($tool1, $toolsProperty['test_tool_1']);
        $this->assertSame($tool2, $toolsProperty['test_tool_2']);

        // 验证处理器被正确设置
        $handler = $multiTool->getToolHandler();
        $this->assertIsCallable($handler);
    }

    /**
     * 测试 execute 方法在无工具调用参数时的返回.
     */
    public function testExecuteWithEmptyToolUses()
    {
        // 创建工具实例
        $multiTool = new MultiToolUseParallelTool();

        // 测试空参数
        $result = $multiTool->execute([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // 测试没有 tool_uses 参数
        $result = $multiTool->execute(['other_param' => 'value']);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // 测试 tool_uses 为空数组
        $result = $multiTool->execute(['tool_uses' => []]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * 测试 execute 方法处理单个工具调用的情况.
     */
    public function testExecuteWithSingleToolCall()
    {
        // 创建测试用的工具定义
        $testTool = new ToolDefinition(
            name: 'calculator',
            description: '计算器工具',
            toolHandler: function ($params) {
                return [
                    'result' => $params['a'] + $params['b'],
                ];
            }
        );

        // 创建工具集合
        $allTools = [
            'calculator' => $testTool,
        ];

        // 创建 MultiToolUseParallelTool 实例
        $multiTool = new MultiToolUseParallelTool($allTools);

        // 构造工具调用参数
        $args = [
            'tool_uses' => [
                [
                    'recipient_name' => 'tools.calculator',
                    'parameters' => [
                        'a' => 5,
                        'b' => 3,
                    ],
                ],
            ],
        ];

        // 执行工具调用
        $results = $multiTool->execute($args);

        // 验证结果
        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertEquals('tools.calculator', $results[0]['recipient_name']);
        $this->assertTrue($results[0]['success']);
        $this->assertIsArray($results[0]['result']);
        $this->assertEquals(8, $results[0]['result']['result']);
    }

    /**
     * 测试 execute 方法处理多个工具并行调用的情况.
     */
    public function testExecuteWithMultipleToolCalls()
    {
        $this->markTestSkipped('协程环境下并行执行测试暂时跳过');

        // 创建测试用的工具定义
        $calculatorTool = new ToolDefinition(
            name: 'calculator',
            description: '计算器工具',
            toolHandler: function ($params) {
                return [
                    'result' => $params['a'] + $params['b'],
                ];
            }
        );

        $multiplyTool = new ToolDefinition(
            name: 'multiply',
            description: '乘法工具',
            toolHandler: function ($params) {
                return [
                    'result' => $params['x'] * $params['y'],
                ];
            }
        );

        $stringTool = new ToolDefinition(
            name: 'concat',
            description: '字符串连接工具',
            toolHandler: function ($params) {
                return [
                    'result' => $params['str1'] . $params['str2'],
                ];
            }
        );

        // 创建工具集合
        $allTools = [
            'calculator' => $calculatorTool,
            'multiply' => $multiplyTool,
            'concat' => $stringTool,
        ];

        // 创建 MultiToolUseParallelTool 实例
        $multiTool = new MultiToolUseParallelTool($allTools);

        // 构造工具调用参数
        $args = [
            'tool_uses' => [
                [
                    'recipient_name' => 'tools.calculator',
                    'parameters' => [
                        'a' => 5,
                        'b' => 3,
                    ],
                ],
                [
                    'recipient_name' => 'tools.multiply',
                    'parameters' => [
                        'x' => 4,
                        'y' => 7,
                    ],
                ],
                [
                    'recipient_name' => 'tools.concat',
                    'parameters' => [
                        'str1' => 'Hello, ',
                        'str2' => 'World!',
                    ],
                ],
            ],
        ];

        // 执行工具调用
        $results = $multiTool->execute($args);

        // 验证结果数量
        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        // 验证每个结果
        $foundCalculator = false;
        $foundMultiply = false;
        $foundConcat = false;

        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertIsArray($result['result']);

            if ($result['recipient_name'] === 'tools.calculator') {
                $this->assertEquals(8, $result['result']['result']);
                $foundCalculator = true;
            } elseif ($result['recipient_name'] === 'tools.multiply') {
                $this->assertEquals(28, $result['result']['result']);
                $foundMultiply = true;
            } elseif ($result['recipient_name'] === 'tools.concat') {
                $this->assertEquals('Hello, World!', $result['result']['result']);
                $foundConcat = true;
            }
        }

        // 确保所有工具都被调用
        $this->assertTrue($foundCalculator, '计算器工具应该被调用');
        $this->assertTrue($foundMultiply, '乘法工具应该被调用');
        $this->assertTrue($foundConcat, '字符串连接工具应该被调用');
    }

    /**
     * 测试工具调用失败时的异常处理.
     */
    public function testExceptionHandling()
    {
        $this->markTestSkipped('协程环境下并行执行测试暂时跳过');

        // 创建一个正常的工具
        $normalTool = new ToolDefinition(
            name: 'normal',
            description: '正常工具',
            toolHandler: function ($params) {
                return ['status' => 'ok'];
            }
        );

        // 创建一个会抛出异常的工具
        $errorTool = new ToolDefinition(
            name: 'error',
            description: '异常工具',
            toolHandler: function ($params) {
                throw new Exception('测试异常');
                return ['status' => 'unreachable'];
            }
        );

        // 创建工具集合
        $allTools = [
            'normal' => $normalTool,
            'error' => $errorTool,
        ];

        // 创建 MultiToolUseParallelTool 实例
        $multiTool = new MultiToolUseParallelTool($allTools);

        // 构造工具调用参数，包含正常和异常工具
        $args = [
            'tool_uses' => [
                [
                    'recipient_name' => 'tools.normal',
                    'parameters' => [],
                ],
                [
                    'recipient_name' => 'tools.error',
                    'parameters' => [],
                ],
            ],
        ];

        // 执行工具调用
        $results = $multiTool->execute($args);

        // 验证结果数量
        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        // 检查每个结果
        $foundNormal = false;
        $foundError = false;

        foreach ($results as $result) {
            if ($result['recipient_name'] === 'tools.normal') {
                $this->assertTrue($result['success'], '正常工具应该成功执行');
                $this->assertEquals(['status' => 'ok'], $result['result']);
                $foundNormal = true;
            } elseif ($result['recipient_name'] === 'tools.error') {
                $this->assertFalse($result['success'], '异常工具应该标记为失败');
                $this->assertIsArray($result['result']);
                $this->assertArrayHasKey('error', $result['result']);
                $this->assertEquals('测试异常', $result['result']['error']);
                $foundError = true;
            }
        }

        // 确保两个工具都被处理
        $this->assertTrue($foundNormal, '正常工具应该被处理');
        $this->assertTrue($foundError, '异常工具应该被处理');
    }

    /**
     * 测试调用不存在的工具时的处理.
     */
    public function testNonExistentTool()
    {
        // 创建一个工具
        $existingTool = new ToolDefinition(
            name: 'existing',
            description: '已存在的工具',
            toolHandler: function ($params) {
                return ['status' => 'ok'];
            }
        );

        // 创建工具集合
        $allTools = [
            'existing' => $existingTool,
        ];

        // 创建 MultiToolUseParallelTool 实例
        $multiTool = new MultiToolUseParallelTool($allTools);

        // 构造工具调用参数，包含存在和不存在的工具
        $args = [
            'tool_uses' => [
                [
                    'recipient_name' => 'tools.existing',
                    'parameters' => [],
                ],
                [
                    'recipient_name' => 'tools.non_existent',
                    'parameters' => [],
                ],
            ],
        ];

        // 执行工具调用
        $results = $multiTool->execute($args);

        // 验证结果
        $this->assertIsArray($results);
        $this->assertCount(1, $results, '应该只返回存在的工具结果');

        // 验证存在的工具结果
        $this->assertEquals('tools.existing', $results[0]['recipient_name']);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(['status' => 'ok'], $results[0]['result']);
    }
}

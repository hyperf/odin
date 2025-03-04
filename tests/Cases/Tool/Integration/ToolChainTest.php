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

namespace HyperfTest\Odin\Cases\Tool\Integration;

use Exception;
use Hyperf\Odin\Exception\ToolParameterValidationException;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;
use InvalidArgumentException;

/**
 * 工具链调用测试
 * 测试多个工具的链式调用和结果传递.
 * @internal
 * @coversNothing
 */
class ToolChainTest extends ToolBaseTestCase
{
    /**
     * 测试多工具链式调用.
     */
    public function testMultipleToolChain(): void
    {
        // 创建第一个工具：字符串转换工具
        $stringTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $text = $parameters['text'] ?? '';
                $operation = $parameters['operation'] ?? 'uppercase';

                if ($operation === 'uppercase') {
                    return ['result' => strtoupper($text)];
                }

                if ($operation === 'lowercase') {
                    return ['result' => strtolower($text)];
                }

                return ['result' => $text];
            }
        };
        $stringTool->setName('string_tool');
        $stringTool->setDescription('字符串操作工具');
        $stringTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => '输入文本',
                ],
                'operation' => [
                    'type' => 'string',
                    'description' => '操作类型',
                    'enum' => ['uppercase', 'lowercase'],
                ],
            ],
            'required' => ['text'],
        ]));

        // 创建第二个工具：字符串附加工具
        $appendTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $text = $parameters['text'] ?? '';
                $suffix = $parameters['suffix'] ?? '';

                return ['result' => $text . $suffix];
            }
        };
        $appendTool->setName('append_tool');
        $appendTool->setDescription('字符串附加工具');
        $appendTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => '输入文本',
                ],
                'suffix' => [
                    'type' => 'string',
                    'description' => '要附加的后缀',
                ],
            ],
            'required' => ['text', 'suffix'],
        ]));

        // 执行工具链调用
        $initialInput = ['text' => 'Hello World', 'operation' => 'uppercase'];
        $stringResult = $stringTool->run($initialInput);

        $this->assertIsArray($stringResult);
        $this->assertArrayHasKey('result', $stringResult);
        $this->assertEquals('HELLO WORLD', $stringResult['result']);

        // 将第一个工具的结果传递给第二个工具
        $appendInput = [
            'text' => $stringResult['result'],
            'suffix' => '!',
        ];
        $finalResult = $appendTool->run($appendInput);

        $this->assertIsArray($finalResult);
        $this->assertArrayHasKey('result', $finalResult);
        $this->assertEquals('HELLO WORLD!', $finalResult['result']);

        // 测试不同的操作链
        $initialInput = ['text' => 'Hello World', 'operation' => 'lowercase'];
        $stringResult = $stringTool->run($initialInput);

        $this->assertEquals('hello world', $stringResult['result']);

        $appendInput = [
            'text' => $stringResult['result'],
            'suffix' => ' - processed',
        ];
        $finalResult = $appendTool->run($appendInput);

        $this->assertEquals('hello world - processed', $finalResult['result']);
    }

    /**
     * 测试工具调用结果传递.
     */
    public function testResultPassing(): void
    {
        // 创建工具链：数据转换链
        // 1. 数字加倍工具
        $doubleTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $number = $parameters['number'] ?? 0;
                return [
                    'result' => $number * 2,
                    'description' => "将 {$number} 加倍得到 " . ($number * 2),
                ];
            }
        };
        $doubleTool->setName('double_tool');
        $doubleTool->setDescription('数字加倍工具');
        $doubleTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'number' => [
                    'type' => 'number',
                    'description' => '输入数字',
                ],
            ],
            'required' => ['number'],
        ]));

        // 2. 数字平方工具
        $squareTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $number = $parameters['number'] ?? 0;
                return [
                    'result' => $number * $number,
                    'description' => "将 {$number} 平方得到 " . ($number * $number),
                ];
            }
        };
        $squareTool->setName('square_tool');
        $squareTool->setDescription('数字平方工具');
        $squareTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'number' => [
                    'type' => 'number',
                    'description' => '输入数字',
                ],
            ],
            'required' => ['number'],
        ]));

        // 3. 数字转文本工具
        $formatTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $number = $parameters['number'] ?? 0;
                $format = $parameters['format'] ?? '结果是: %d';

                return [
                    'result' => sprintf($format, $number),
                ];
            }
        };
        $formatTool->setName('format_tool');
        $formatTool->setDescription('数字格式化工具');
        $formatTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'number' => [
                    'type' => 'number',
                    'description' => '输入数字',
                ],
                'format' => [
                    'type' => 'string',
                    'description' => '格式化字符串',
                ],
            ],
            'required' => ['number'],
        ]));

        // 执行工具链
        // 初始值: 5
        $doubleResult = $doubleTool->run(['number' => 5]);
        $this->assertEquals(10, $doubleResult['result']);
        $this->assertEquals('将 5 加倍得到 10', $doubleResult['description']);

        // 传递结果
        $squareResult = $squareTool->run(['number' => $doubleResult['result']]);
        $this->assertEquals(100, $squareResult['result']);
        $this->assertEquals('将 10 平方得到 100', $squareResult['description']);

        // 最终格式化
        $formatResult = $formatTool->run([
            'number' => $squareResult['result'],
            'format' => '计算结果: %d',
        ]);
        $this->assertEquals('计算结果: 100', $formatResult['result']);

        // 测试不同参数
        $doubleResult = $doubleTool->run(['number' => 3.5]);
        $this->assertEquals(7, $doubleResult['result']);

        $squareResult = $squareTool->run(['number' => $doubleResult['result']]);
        $this->assertEquals(49, $squareResult['result']);

        $formatResult = $formatTool->run([
            'number' => $squareResult['result'],
            'format' => '最终值 = %d',
        ]);
        $this->assertEquals('最终值 = 49', $formatResult['result']);
    }

    /**
     * 测试错误处理和恢复.
     */
    public function testErrorHandlingAndRecovery(): void
    {
        // 创建带有错误处理的工具链
        // 1. 除法工具 - 可能抛出除零异常
        $divideTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $dividend = $parameters['dividend'] ?? 0;
                $divisor = $parameters['divisor'] ?? 1;

                if ($divisor == 0) {
                    throw new InvalidArgumentException('除数不能为零');
                }

                return [
                    'result' => $dividend / $divisor,
                    'operation' => 'division',
                ];
            }
        };
        $divideTool->setName('divide_tool');
        $divideTool->setDescription('除法工具');
        $divideTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'dividend' => [
                    'type' => 'number',
                    'description' => '被除数',
                ],
                'divisor' => [
                    'type' => 'number',
                    'description' => '除数',
                ],
            ],
            'required' => ['dividend', 'divisor'],
        ]));

        // 2. 错误处理和恢复工具
        $recoveryTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $errorType = $parameters['error_type'] ?? '';
                $defaultValue = $parameters['default_value'] ?? 0;

                if ($errorType === 'division_by_zero') {
                    return [
                        'result' => $defaultValue,
                        'message' => '除零错误已恢复，使用默认值',
                        'status' => 'recovered',
                    ];
                }

                return [
                    'result' => $defaultValue,
                    'message' => '未知错误，使用默认值',
                    'status' => 'unknown_recovery',
                ];
            }
        };
        $recoveryTool->setName('recovery_tool');
        $recoveryTool->setDescription('错误恢复工具');
        $recoveryTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'error_type' => [
                    'type' => 'string',
                    'description' => '错误类型',
                ],
                'default_value' => [
                    'type' => 'number',
                    'description' => '默认返回值',
                ],
            ],
            'required' => ['error_type'],
        ]));

        // 测试正常情况
        $divideResult = $divideTool->run(['dividend' => 10, 'divisor' => 2]);
        $this->assertEquals(5, $divideResult['result']);

        // 测试错误情况及恢复
        try {
            // 尝试除以零，会抛出异常
            $divideTool->run(['dividend' => 10, 'divisor' => 0]);
            $this->fail('除以零应该抛出异常');
        } catch (Exception $e) {
            // 捕获异常并使用恢复工具处理
            $this->assertStringContainsString('除数不能为零', $e->getMessage());

            // 使用恢复工具处理错误
            $recoveryResult = $recoveryTool->run([
                'error_type' => 'division_by_zero',
                'default_value' => 999,
            ]);

            $this->assertEquals(999, $recoveryResult['result']);
            $this->assertEquals('recovered', $recoveryResult['status']);
            $this->assertStringContainsString('除零错误已恢复', $recoveryResult['message']);
        }

        // 测试参数验证失败的情况
        try {
            // 缺少必需参数，会导致验证失败
            $divideTool->run(['dividend' => 10]); // 缺少divisor
            $this->fail('缺少必填参数应该抛出异常');
        } catch (ToolParameterValidationException $e) {
            // 验证异常信息
            $this->assertStringContainsString('工具参数验证失败', $e->getMessage());

            // 使用恢复工具处理错误
            $recoveryResult = $recoveryTool->run([
                'error_type' => 'missing_parameter',
                'default_value' => 0,
            ]);

            $this->assertEquals(0, $recoveryResult['result']);
            $this->assertEquals('unknown_recovery', $recoveryResult['status']);
        }
    }
}

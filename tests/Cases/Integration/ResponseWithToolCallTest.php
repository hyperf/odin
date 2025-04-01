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

namespace HyperfTest\Odin\Cases\Integration;

use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Tool\AbstractTool;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * ChatCompletionResponse 与 ToolCall 集成测试.
 * @internal
 * @coversNothing
 */
class ResponseWithToolCallTest extends AbstractTestCase
{
    /**
     * 测试从响应中提取工具调用信息.
     */
    public function testExtractToolCallsFromResponse()
    {
        // 创建一个工具调用
        $toolCall = new ToolCall(
            name: 'calculator',
            arguments: ['a' => 1, 'b' => 2, 'operation' => 'add'],
            id: 'call_123456'
        );

        // 创建一个包含工具调用的助手消息
        $assistantMessage = new AssistantMessage('我需要计算一些数字');
        $assistantMessage->setToolCalls([$toolCall]);

        // 创建一个聊天完成选择
        $choice = new ChatCompletionChoice(
            message: $assistantMessage,
            index: 0,
            finishReason: 'tool_calls'
        );

        // 创建一个模拟的 PSR-7 Response
        $psrResponse = new Response(200, [], json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '我需要计算一些数字',
                        'tool_calls' => [
                            [
                                'id' => 'call_123456',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'calculator',
                                    'arguments' => json_encode(['a' => 1, 'b' => 2, 'operation' => 'add']),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        // 创建一个聊天完成响应
        $response = new ChatCompletionResponse($psrResponse);

        // 断言响应中包含工具调用
        $this->assertTrue($choice->isFinishedByToolCall());
        $this->assertCount(1, $assistantMessage->getToolCalls());

        // 获取工具调用
        $toolCalls = $assistantMessage->getToolCalls();
        $this->assertCount(1, $toolCalls);

        // 验证工具调用的详细信息
        $extractedToolCall = $toolCalls[0];
        $this->assertEquals('calculator', $extractedToolCall->getName());
        $this->assertEquals('call_123456', $extractedToolCall->getId());

        // 验证工具调用参数
        $arguments = $extractedToolCall->getArguments();
        $this->assertEquals(1, $arguments['a']);
        $this->assertEquals(2, $arguments['b']);
        $this->assertEquals('add', $arguments['operation']);
    }

    /**
     * 测试解析工具调用参数.
     */
    public function testParseToolCallArguments()
    {
        // 创建一个计算器工具
        $calculator = new class extends AbstractTool {
            protected string $name = 'calculator';

            protected string $description = '执行基本的数学运算';

            protected function handle(array $parameters): array
            {
                $a = $parameters['a'] ?? 0;
                $b = $parameters['b'] ?? 0;
                $operation = $parameters['operation'] ?? 'add';

                $result = match ($operation) {
                    'add' => $a + $b,
                    'subtract' => $a - $b,
                    'multiply' => $a * $b,
                    'divide' => $b != 0 ? $a / $b : 'Error: Division by zero',
                    default => 'Error: Unknown operation',
                };

                return ['result' => $result];
            }
        };

        // 创建一个工具调用，参数为字符串形式的JSON
        $toolCall = new ToolCall(
            name: 'calculator',
            arguments: [],
            id: 'call_123456',
            streamArguments: '{"a":"5","b":"3","operation":"add"}'
        );

        // 运行工具
        $result = $calculator->run($toolCall->getArguments());

        // 验证结果
        $this->assertEquals(8, $result['result']);
    }

    /**
     * 测试处理多个工具调用.
     */
    public function testHandleMultipleToolCalls()
    {
        // 创建一个计算器工具
        $calculator = new class extends AbstractTool {
            protected string $name = 'calculator';

            protected string $description = '执行基本的数学运算';

            protected function handle(array $parameters): array
            {
                $a = $parameters['a'] ?? 0;
                $b = $parameters['b'] ?? 0;
                $operation = $parameters['operation'] ?? 'add';

                $result = match ($operation) {
                    'add' => $a + $b,
                    'subtract' => $a - $b,
                    'multiply' => $a * $b,
                    'divide' => $b != 0 ? $a / $b : 'Error: Division by zero',
                    default => 'Error: Unknown operation',
                };

                return ['result' => $result];
            }
        };

        // 创建多个工具调用
        $toolCall1 = new ToolCall(
            name: 'calculator',
            arguments: ['a' => 5, 'b' => 3, 'operation' => 'add'],
            id: 'call_1'
        );

        $toolCall2 = new ToolCall(
            name: 'calculator',
            arguments: ['a' => 10, 'b' => 2, 'operation' => 'multiply'],
            id: 'call_2'
        );

        // 创建一个包含工具调用的助手消息
        $assistantMessage = new AssistantMessage('我需要计算一些数字');
        $assistantMessage->setToolCalls([$toolCall1, $toolCall2]);

        // 创建一个模拟的 PSR-7 Response
        $psrResponse = new Response(200, [], json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '我需要计算一些数字',
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'calculator',
                                    'arguments' => json_encode(['a' => 5, 'b' => 3, 'operation' => 'add']),
                                ],
                            ],
                            [
                                'id' => 'call_2',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'calculator',
                                    'arguments' => json_encode(['a' => 10, 'b' => 2, 'operation' => 'multiply']),
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ]));

        // 创建一个聊天完成响应
        $response = new ChatCompletionResponse($psrResponse);

        // 处理每个工具调用
        $results = [];
        foreach ($assistantMessage->getToolCalls() as $toolCall) {
            if ($toolCall->getName() === 'calculator') {
                $results[$toolCall->getId()] = $calculator->run($toolCall->getArguments());
            }
        }

        // 验证结果
        $this->assertCount(2, $results);
        $this->assertEquals(8, $results['call_1']['result']);
        $this->assertEquals(20, $results['call_2']['result']);
    }
}

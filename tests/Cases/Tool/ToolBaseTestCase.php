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

use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * Tool模块测试的基础测试类
 * 提供Tool相关的测试辅助方法.
 * @internal
 * @coversNothing
 */
class ToolBaseTestCase extends AbstractTestCase
{
    /**
     * 创建一个用于测试的简单工具定义.
     */
    protected function createSimpleToolDefinition(string $name = 'test_tool', string $description = '测试工具'): ToolDefinition
    {
        return new ToolDefinition(
            name: $name,
            description: $description,
            parameters: new ToolParameters([
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
            ])
        );
    }

    /**
     * 创建一个复杂的工具定义（包含嵌套结构和多种参数类型）.
     */
    protected function createComplexToolDefinition(string $name = 'complex_tool', string $description = '复杂测试工具'): ToolDefinition
    {
        return new ToolDefinition(
            name: $name,
            description: $description,
            parameters: new ToolParameters([
                'type' => 'object',
                'properties' => [
                    'user' => [
                        'type' => 'object',
                        'description' => '用户信息',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => '用户名称',
                            ],
                            'age' => [
                                'type' => 'integer',
                                'description' => '用户年龄',
                                'minimum' => 0,
                                'maximum' => 150,
                            ],
                            'email' => [
                                'type' => 'string',
                                'description' => '电子邮件',
                                'format' => 'email',
                            ],
                        ],
                        'required' => ['name', 'email'],
                    ],
                    'preferences' => [
                        'type' => 'array',
                        'description' => '偏好设置',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                    'settings' => [
                        'type' => 'object',
                        'description' => '设置信息',
                        'properties' => [
                            'theme' => [
                                'type' => 'string',
                                'enum' => ['light', 'dark', 'auto'],
                                'description' => '主题',
                            ],
                            'notifications' => [
                                'type' => 'boolean',
                                'description' => '是否开启通知',
                            ],
                            'displayMode' => [
                                'type' => 'string',
                                'enum' => ['simple', 'advanced'],
                                'description' => '显示模式',
                            ],
                        ],
                    ],
                ],
                'required' => ['user'],
            ])
        );
    }

    /**
     * 获取示例工具调用参数.
     */
    protected function getSampleToolCallArguments(): array
    {
        return [
            'simple' => [
                'name' => '测试用户',
                'age' => 30,
            ],
            'complex' => [
                'user' => [
                    'name' => '张三',
                    'age' => 28,
                    'email' => 'zhangsan@example.com',
                ],
                'preferences' => ['reading', 'coding', 'music'],
                'settings' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'displayMode' => 'advanced',
                ],
            ],
        ];
    }

    /**
     * 获取示例工具调用响应.
     */
    protected function getSampleToolCallResponse(): array
    {
        return [
            'id' => 'call_01234567890abcdef',
            'type' => 'function',
            'function' => [
                'name' => 'test_tool',
                'arguments' => '{"name":"测试用户","age":30}',
            ],
        ];
    }

    /**
     * 创建模拟的API响应数据，包含工具调用.
     */
    protected function createMockApiResponseWithToolCall(): array
    {
        return [
            'id' => 'chatcmpl-123456789',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-4',
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150,
            ],
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_01234567890abcdef',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'test_tool',
                                    'arguments' => '{"name":"测试用户","age":30}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ];
    }
}

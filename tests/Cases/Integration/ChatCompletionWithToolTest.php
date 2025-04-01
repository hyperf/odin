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

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * ChatCompletionRequest 与 Tool 集成测试.
 * @internal
 * @coversNothing
 */
class ChatCompletionWithToolTest extends AbstractTestCase
{
    /**
     * 测试将工具定义转换为 API 请求参数.
     */
    public function testToolDefinitionToRequestParams(): void
    {
        // 创建一个简单的工具定义
        $definition = new ToolDefinition(
            'test_tool',
            '测试工具',
            ToolParameters::fromArray([
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
            ]),
            function ($params) {
                return ['result' => true];
            }
        );

        // 创建一个包含工具的 ChatCompletionRequest
        $request = new ChatCompletionRequest(
            messages: [
                new UserMessage('使用测试工具'),
            ],
            model: 'gpt-4',
            tools: [$definition],
        );

        // 生成请求参数
        $request->validate();
        $options = $request->createOptions();

        // 验证请求参数是否正确
        $this->assertIsArray($options);
        $this->assertArrayHasKey(RequestOptions::JSON, $options);
        $json = $options[RequestOptions::JSON];

        // 验证工具信息
        $this->assertArrayHasKey('tools', $json);
        $this->assertCount(1, $json['tools']);
        $this->assertEquals('function', $json['tools'][0]['type']);
        $this->assertEquals('test_tool', $json['tools'][0]['function']['name']);
        $this->assertEquals('测试工具', $json['tools'][0]['function']['description']);

        // 验证工具参数
        $this->assertArrayHasKey('parameters', $json['tools'][0]['function']);
        $this->assertEquals('object', $json['tools'][0]['function']['parameters']['type']);
        $this->assertArrayHasKey('properties', $json['tools'][0]['function']['parameters']);
        $this->assertArrayHasKey('name', $json['tools'][0]['function']['parameters']['properties']);
        $this->assertArrayHasKey('age', $json['tools'][0]['function']['parameters']['properties']);
        $this->assertEquals(['name'], $json['tools'][0]['function']['parameters']['required']);

        // 验证工具选择策略
        $this->assertArrayHasKey('tool_choice', $json);
        $this->assertEquals('auto', $json['tool_choice']);
    }

    /**
     * 测试多种工具类型的处理.
     */
    public function testVariousToolTypeHandling(): void
    {
        // 1. 创建一个使用 ToolDefinition 的工具
        $definition1 = new ToolDefinition(
            'calculator',
            '计算器工具',
            ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'description' => '操作',
                        'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    ],
                    'a' => [
                        'type' => 'number',
                        'description' => '第一个数',
                    ],
                    'b' => [
                        'type' => 'number',
                        'description' => '第二个数',
                    ],
                ],
                'required' => ['operation', 'a', 'b'],
            ]),
            function ($params) {
                return ['result' => true];
            }
        );

        // 2. 创建一个继承 AbstractTool 的工具
        $weatherTool = new class extends AbstractTool {
            protected string $name = 'weather';

            protected string $description = '天气查询工具';

            protected function handle(array $parameters): array
            {
                return ['temperature' => 25, 'condition' => 'sunny'];
            }
        };
        $weatherTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => '城市名称',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => '日期',
                    'enum' => ['today', 'tomorrow'],
                ],
            ],
            'required' => ['city'],
        ]));

        // 3. 创建一个原始数组形式的工具
        $rawTool = [
            'type' => 'function',
            'function' => [
                'name' => 'search',
                'description' => '搜索工具',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => '搜索关键词',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => '结果数量限制',
                            'default' => 10,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];

        // 创建包含多种工具的请求
        $request = new ChatCompletionRequest(
            messages: [
                new UserMessage('请使用工具帮我解决问题'),
            ],
            model: 'gpt-4',
            tools: [$definition1, $weatherTool, $rawTool],
        );

        // 生成请求参数
        $request->validate();
        $options = $request->createOptions();
        $json = $options[RequestOptions::JSON];

        // 验证工具数量
        $this->assertArrayHasKey('tools', $json);
        $this->assertCount(3, $json['tools']);

        // 验证工具名称
        $toolNames = array_map(fn ($tool) => $tool['function']['name'], $json['tools']);
        $this->assertContains('calculator', $toolNames);
        $this->assertContains('weather', $toolNames);
        $this->assertContains('search', $toolNames);

        // 验证每个工具的结构
        foreach ($json['tools'] as $tool) {
            $this->assertEquals('function', $tool['type']);
            $this->assertArrayHasKey('function', $tool);
            $this->assertArrayHasKey('name', $tool['function']);
            $this->assertArrayHasKey('description', $tool['function']);
            $this->assertArrayHasKey('parameters', $tool['function']);
        }
    }

    /**
     * 测试工具选择策略实现.
     */
    public function testToolChoiceStrategies(): void
    {
        // 创建一个简单的工具
        $definition = new ToolDefinition(
            'simple_tool',
            '简单工具',
            ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => '输入',
                    ],
                ],
                'required' => ['input'],
            ]),
            function ($params) {
                return ['result' => true];
            }
        );

        // 1. 测试 auto 策略 (默认)
        $request1 = new ChatCompletionRequest(
            messages: [new UserMessage('测试')],
            model: 'gpt-4',
            tools: [$definition],
        );
        $request1->validate();
        $options1 = $request1->createOptions();
        $json1 = $options1[RequestOptions::JSON];

        $this->assertArrayHasKey('tool_choice', $json1);
        $this->assertEquals('auto', $json1['tool_choice']);

        // 2. 测试 "none" 策略 (不使用工具)
        // 注: 由于当前 ChatCompletionRequest 类没有直接支持设置 tool_choice 为 "none"
        // 因此这里我们手动构造一个没有工具的请求来验证
        $request2 = new ChatCompletionRequest(
            messages: [new UserMessage('测试')],
            model: 'gpt-4',
            tools: [], // 空工具列表
        );
        $request2->validate();
        $options2 = $request2->createOptions();
        $json2 = $options2[RequestOptions::JSON];

        // 验证没有 tool_choice 字段
        $this->assertArrayNotHasKey('tool_choice', $json2);
        $this->assertArrayNotHasKey('tools', $json2);

        // 3. 测试 "required" 策略 (强制使用特定工具)
        // 注: 这需要 ChatCompletionRequest 类支持设置特定工具
        // 目前的实现可能不支持这种情况，这里我们可以提出一个改进建议

        // 建议: 未来可以增强 ChatCompletionRequest 类，添加设置 tool_choice 的方法
        // 例如:
        // $request->setToolChoice('required');
        // $request->setToolChoice(['type' => 'function', 'function' => ['name' => 'simple_tool']]);
    }
}

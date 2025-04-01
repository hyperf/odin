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

namespace HyperfTest\Odin\Cases\Api\Request;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Throwable;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Request\ChatCompletionRequest
 */
class ChatCompletionRequestTest extends AbstractTestCase
{
    /**
     * 测试基本构造和参数设置.
     */
    public function testBasicConstruction()
    {
        $messages = [
            new SystemMessage('你是一个有用的助手'),
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 0.7,
            maxTokens: 50,
            stop: ['PHP'],
            tools: [],
            stream: false
        );

        // 验证构造函数正确设置了属性
        $this->assertEquals($messages, $request->getMessages());
        $this->assertEquals('gpt-3.5-turbo', $request->getModel());
        $this->assertEquals([], $request->getTools());
        $this->assertFalse($request->isStream());
    }

    /**
     * 测试validate方法（有效输入）.
     */
    public function testValidateWithValidInput()
    {
        $messages = [
            new SystemMessage('你是一个有用的助手'),
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 0.7
        );

        // 应该不抛出异常
        try {
            $request->validate();
            $this->assertTrue(true); // 如果没有异常，测试通过
        } catch (Throwable $e) {
            $this->fail('有效输入不应抛出异常：' . $e->getMessage());
        }
    }

    /**
     * 测试validate方法（无效模型）.
     */
    public function testValidateWithEmptyModel()
    {
        $messages = [
            new SystemMessage('你是一个有用的助手'),
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: '', // 空模型名称
            temperature: 0.7
        );

        // 应该抛出异常
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required.');
        $request->validate();
    }

    /**
     * 测试validate方法（无效温度）.
     */
    public function testValidateWithInvalidTemperature()
    {
        $messages = [
            new SystemMessage('你是一个有用的助手'),
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 1.5 // 超出0-1范围
        );

        // 应该抛出异常
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0 and 1.');
        $request->validate();
    }

    /**
     * 测试validate方法（空消息）.
     */
    public function testValidateWithEmptyMessages()
    {
        $request = new ChatCompletionRequest(
            messages: [], // 空消息列表
            model: 'gpt-3.5-turbo',
            temperature: 0.7
        );

        // 应该抛出异常
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Messages is required.');
        $request->validate();
    }

    /**
     * 测试createOptions方法生成正确的请求选项.
     */
    public function testCreateOptions()
    {
        $messages = [
            new SystemMessage('你是一个有用的助手'),
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 0.7,
            maxTokens: 50,
            stop: ['PHP'],
            tools: [],
            stream: false
        );

        // 先调用validate确保filterMessages被设置
        $request->validate();

        // 获取选项
        $options = $request->createOptions();

        // 验证基本选项
        $this->assertIsArray($options);
        $this->assertArrayHasKey(RequestOptions::JSON, $options);
        $this->assertArrayHasKey(RequestOptions::STREAM, $options);
        $this->assertFalse($options[RequestOptions::STREAM]);

        // 验证JSON选项
        $json = $options[RequestOptions::JSON];
        $this->assertEquals('gpt-3.5-turbo', $json['model']);
        $this->assertEquals(0.7, $json['temperature']);
        $this->assertEquals(50, $json['max_tokens']);
        $this->assertEquals(['PHP'], $json['stop']);
        $this->assertFalse($json['stream']);

        // 验证消息正确转换为数组
        $this->assertCount(2, $json['messages']);
        $this->assertEquals('system', $json['messages'][0]['role']);
        $this->assertEquals('你是一个有用的助手', $json['messages'][0]['content']);
        $this->assertEquals('user', $json['messages'][1]['role']);
        $this->assertEquals('用一句话介绍PHP语言', $json['messages'][1]['content']);
    }

    /**
     * 测试createOptions在消息中包含空系统消息时能正确过滤.
     */
    public function testCreateOptionsFiltersEmptySystemMessage()
    {
        $messages = [
            new SystemMessage(''), // 空系统消息
            new UserMessage('用一句话介绍PHP语言'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 0.7
        );

        // 获取选项
        $options = $request->createOptions();
        $json = $options[RequestOptions::JSON];

        // 验证空系统消息被过滤
        $this->assertCount(1, $json['messages']);
        $this->assertEquals('user', $json['messages'][0]['role']);
    }

    /**
     * 测试工具参数处理.
     */
    public function testToolsParameterHandling()
    {
        // 创建模拟工具
        $mockTool = $this->createMock(ToolInterface::class);
        $toolDefinition = new ToolDefinition(
            name: 'test_tool',
            description: '测试工具',
            parameters: new ToolParameters([
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string'],
                ],
            ]),
            toolHandler: function ($params) { return $params; } // 添加可调用的处理器
        );
        $mockTool->method('toToolDefinition')->willReturn($toolDefinition);

        // 创建直接使用ToolDefinition的测试
        $directDefinition = new ToolDefinition(
            name: 'direct_tool',
            description: '直接定义工具',
            parameters: new ToolParameters([
                'type' => 'object',
                'properties' => [
                    'param2' => ['type' => 'number'],
                ],
            ]),
            toolHandler: function ($params) { return $params; } // 添加可调用的处理器
        );

        // 创建原始数组工具
        $arrayTool = [
            'type' => 'function',
            'function' => [
                'name' => 'array_tool',
                'description' => '数组工具',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'param3' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ];

        $messages = [
            new UserMessage('使用工具'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            temperature: 0.7,
            tools: [$mockTool, $directDefinition, $arrayTool]
        );

        // 获取选项
        $options = $request->createOptions();
        $json = $options[RequestOptions::JSON];

        // 验证工具参数
        $this->assertArrayHasKey('tools', $json);
        $this->assertCount(3, $json['tools']);
        $this->assertArrayHasKey('tool_choice', $json);
        $this->assertEquals('auto', $json['tool_choice']);

        // 验证第一个工具(ToolInterface)
        $this->assertEquals('test_tool', $json['tools'][0]['function']['name']);

        // 验证第二个工具(ToolDefinition)
        $this->assertEquals('direct_tool', $json['tools'][1]['function']['name']);

        // 验证第三个工具(数组)
        $this->assertEquals('array_tool', $json['tools'][2]['function']['name']);
    }

    /**
     * 测试流式响应相关功能.
     */
    public function testStreamRelatedFunctions()
    {
        $messages = [
            new UserMessage('测试流'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo',
            stream: true
        );

        // 测试流式标志
        $this->assertTrue($request->isStream());

        // 测试设置流式标志
        $request->setStream(false);
        $this->assertFalse($request->isStream());

        // 测试内容流式标志
        $this->assertFalse($request->isStreamContentEnabled());
        $request->setStreamContentEnabled(true);
        $this->assertTrue($request->isStreamContentEnabled());
    }
}

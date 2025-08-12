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
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameter;
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

        // 设置选项键映射
        $request->setOptionKeyMaps(['max_tokens' => 'max_completion_tokens']);

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
        $this->assertEquals(50, $json['max_completion_tokens']);
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

    public function testCalculateTokenEstimates()
    {
        // 准备测试消息和工具
        $systemMessage = new SystemMessage('你是一个有用的AI助手');
        $userMessage = new UserMessage('请帮我解答这个问题');

        $toolParameter = ToolParameter::string('query', '查询参数', true);
        $toolParameters = new ToolParameters([$toolParameter]);
        $toolHandler = function (array $params) {
            return ['result' => 'test result'];
        };
        $tool = new ToolDefinition('search', '搜索工具', $toolParameters, $toolHandler);

        // 创建请求对象
        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'gpt-4',
            0.7,
            100,
            [],
            [$tool]
        );

        // 执行token估算前，总token估算应为null
        $this->assertNull($request->getTotalTokenEstimate());

        // 执行token估算
        $totalTokens = $request->calculateTokenEstimates();

        // 验证结果
        $this->assertIsInt($totalTokens);
        $this->assertGreaterThan(0, $totalTokens);

        // 验证消息的token估算已设置
        $this->assertNotNull($systemMessage->getTokenEstimate());
        $this->assertNotNull($userMessage->getTokenEstimate());

        // 验证工具的token估算已设置
        $this->assertNotNull($request->getToolsTokenEstimate());

        // 验证总token估算已设置，并与返回值相同
        $this->assertNotNull($request->getTotalTokenEstimate());
        $this->assertEquals($totalTokens, $request->getTotalTokenEstimate());

        // 验证总token是各部分之和
        $this->assertEquals(
            $systemMessage->getTokenEstimate() + $userMessage->getTokenEstimate() + $request->getToolsTokenEstimate(),
            $request->getTotalTokenEstimate()
        );
    }

    public function testCalculateTokenEstimatesWithExistingEstimates()
    {
        // 准备已有估算值的消息
        $systemMessage = new SystemMessage('你是一个有用的AI助手');
        $systemMessage->setTokenEstimate(10);

        $userMessage = new UserMessage('请帮我解答这个问题');
        // 用户消息不设置估算值

        // 创建请求对象
        $request = new ChatCompletionRequest(
            [$systemMessage, $userMessage],
            'gpt-4'
        );

        // 执行token估算
        $totalTokens = $request->calculateTokenEstimates();

        // 验证结果
        $this->assertIsInt($totalTokens);

        // 验证已设置估算值的消息保持不变
        $this->assertEquals(10, $systemMessage->getTokenEstimate());

        // 验证未设置估算值的消息被计算
        $this->assertNotNull($userMessage->getTokenEstimate());

        // 验证总token已设置
        $this->assertNotNull($request->getTotalTokenEstimate());
        $this->assertEquals($totalTokens, $request->getTotalTokenEstimate());

        // 验证总token是消息token之和（没有工具的情况）
        $this->assertEquals(
            $systemMessage->getTokenEstimate() + $userMessage->getTokenEstimate(),
            $request->getTotalTokenEstimate()
        );
    }

    // ==================== 消息序列验证测试 ====================

    /**
     * 测试正常的消息序列 - 简单对话.
     */
    public function testValidateMessageSequenceNormalConversation()
    {
        $messages = [
            new UserMessage('你好'),
            new AssistantMessage('你好！有什么我可以帮助你的吗？'),
            new UserMessage('今天天气怎么样？'),
            new AssistantMessage('我无法获取实时天气信息，建议你查看天气应用或网站。'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        // 不应该抛出异常
        $this->assertNotThrows(function () use ($request) {
            $request->validate();
        });
    }

    /**
     * 测试正常的消息序列 - 完整工具调用流程.
     */
    public function testValidateMessageSequenceCompleteToolCallFlow()
    {
        $toolCall = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');

        $messages = [
            new UserMessage('北京天气怎么样？'),
            new AssistantMessage('让我查询北京的天气信息。', [$toolCall]),
            new ToolMessage('北京今天晴，温度25°C', 'tool-123'),
            new AssistantMessage('根据查询结果，北京今天晴，温度25°C。'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->assertNotThrows(function () use ($request) {
            $request->validate();
        });
    }

    /**
     * 测试正常的消息序列 - 多个工具调用.
     */
    public function testValidateMessageSequenceMultipleToolCalls()
    {
        $toolCall1 = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');
        $toolCall2 = new ToolCall('translate_tool', ['text' => 'hello'], 'tool-456');

        $messages = [
            new UserMessage('查询北京天气并翻译hello'),
            new AssistantMessage('我将为你查询北京天气并翻译hello。', [$toolCall1, $toolCall2]),
            new ToolMessage('北京今天晴，温度25°C', 'tool-123'),
            new ToolMessage('你好', 'tool-456'),
            new AssistantMessage('北京今天晴，温度25°C。hello的中文翻译是：你好。'),
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->assertNotThrows(function () use ($request) {
            $request->validate();
        });
    }

    /**
     * 测试错误场景 - 连续的assistant消息.
     */
    public function testValidateMessageSequenceConsecutiveAssistantMessages()
    {
        $toolCall = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');

        $messages = [
            new UserMessage('查询天气'),
            new AssistantMessage('让我查询天气信息。', [$toolCall]),
            new AssistantMessage('抱歉，查询被中断了。'), // 错误：连续的assistant消息
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Assistant message with tool calls at position 1 must be followed by tool result messages/');
        $this->expectExceptionMessageMatches('/Tool calls: weather_tool\(id:tool-123\)/');
        $this->expectExceptionMessageMatches('/Solution: After an assistant message with tool_calls/');

        $request->validate();
    }

    /**
     * 测试正常场景 - 连续的assistant消息（没有tool calls）应该是允许的.
     */
    public function testValidateMessageSequenceConsecutiveAssistantMessagesWithoutToolCalls()
    {
        $messages = [
            new UserMessage('Hello'),
            new AssistantMessage('Hi there'),
            new AssistantMessage('How can I help?'), // 连续的assistant消息，但前一个没有tool calls
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        // 应该不抛出异常
        $this->assertNotThrows(function () use ($request) {
            $request->validate();
        });
    }

    /**
     * 测试错误场景 - 有工具调用但缺少工具结果消息.
     */
    public function testValidateMessageSequenceMissingToolResults()
    {
        $toolCall = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');

        $messages = [
            new UserMessage('查询天气'),
            new AssistantMessage('让我查询天气信息。', [$toolCall]),
            // 缺少ToolMessage
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Missing tool result messages for pending tool_calls/');
        $this->expectExceptionMessageMatches('/Pending tool_call IDs: tool-123/');
        $this->expectExceptionMessageMatches('/Expected sequence:/');

        $request->validate();
    }

    /**
     * 测试错误场景 - 工具消息没有对应的工具调用.
     */
    public function testValidateMessageSequenceUnexpectedToolMessage()
    {
        $messages = [
            new UserMessage('你好'),
            new AssistantMessage('你好！'),
            new ToolMessage('天气查询结果', 'tool-123'), // 错误：没有对应的工具调用
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Found unexpected tool message at position 2/');
        $this->expectExceptionMessageMatches('/Tool call ID: tool-123/');
        $this->expectExceptionMessageMatches('/Problem: This tool message appears without a preceding assistant message/');

        $request->validate();
    }

    /**
     * 测试错误场景 - 工具消息ID不匹配.
     */
    public function testValidateMessageSequenceMismatchedToolCallId()
    {
        $toolCall = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');

        $messages = [
            new UserMessage('查询天气'),
            new AssistantMessage('让我查询天气信息。', [$toolCall]),
            new ToolMessage('天气查询结果', 'tool-456'), // 错误：ID不匹配
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Tool message ID mismatch at position 2/');
        $this->expectExceptionMessageMatches('/Expected tool_call IDs: tool-123/');
        $this->expectExceptionMessageMatches('/Found tool_call ID: tool-456/');

        $request->validate();
    }

    /**
     * 测试错误场景 - 部分工具调用缺少结果.
     */
    public function testValidateMessageSequencePartialToolResults()
    {
        $toolCall1 = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');
        $toolCall2 = new ToolCall('translate_tool', ['text' => 'hello'], 'tool-456');

        $messages = [
            new UserMessage('查询天气并翻译'),
            new AssistantMessage('我将为你查询天气并翻译。', [$toolCall1, $toolCall2]),
            new ToolMessage('北京今天晴', 'tool-123'),
            // 缺少tool-456的结果消息
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Missing tool result messages for pending tool_calls/');
        $this->expectExceptionMessageMatches('/Pending tool_call IDs: tool-456/');

        $request->validate();
    }

    /**
     * 测试错误场景 - 有待处理工具调用时遇到新的assistant消息.
     */
    public function testValidateMessageSequenceAssistantMessageWithPendingToolCalls()
    {
        $toolCall1 = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');
        $toolCall2 = new ToolCall('translate_tool', ['text' => 'hello'], 'tool-456');

        $messages = [
            new UserMessage('查询天气并翻译'),
            new AssistantMessage('我将为你查询天气并翻译。', [$toolCall1, $toolCall2]),
            new ToolMessage('北京今天晴', 'tool-123'),
            new AssistantMessage('让我继续处理翻译。'), // 错误：还有未处理的tool-456
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        $this->expectExceptionMessageMatches('/Invalid message sequence: Expected tool result messages for pending tool_calls/');
        $this->expectExceptionMessageMatches('/Pending tool_call IDs: tool-456/');
        $this->expectExceptionMessageMatches('/Current assistant message at position 3/');

        $request->validate();
    }

    /**
     * 测试边界场景 - 空消息数组（应该通过验证）.
     */
    public function testValidateMessageSequenceEmptyMessages()
    {
        $messages = [];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        // 消息序列验证应该通过，但会在其他验证中失败（因为消息为空）
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Messages is required.');

        $request->validate();
    }

    /**
     * 测试内容截断功能.
     */
    public function testValidateMessageSequenceContentTruncation()
    {
        $longContent = str_repeat('这是一个很长的消息内容，用来测试内容截断功能。', 10); // 超过100字符
        $toolCall = new ToolCall('weather_tool', ['location' => 'Beijing'], 'tool-123');

        $messages = [
            new UserMessage('查询天气'),
            new AssistantMessage($longContent, [$toolCall]), // 包含tool calls
            new AssistantMessage('另一条消息'), // 错误：应该是tool消息
        ];

        $request = new ChatCompletionRequest(
            messages: $messages,
            model: 'gpt-3.5-turbo'
        );

        $this->expectException(LLMModelException::class);
        // 验证长内容被截断
        $this->expectExceptionMessageMatches('/Content: ".*\.\.\."/');

        $request->validate();
    }

    /**
     * 辅助方法：验证不抛出异常.
     */
    private function assertNotThrows(callable $callback, string $message = '')
    {
        try {
            $callback();
            $this->assertTrue(true, $message ?: '不应抛出异常');
        } catch (Throwable $e) {
            $this->fail(($message ?: '不应抛出异常') . '，但抛出了：' . $e->getMessage());
        }
    }
}

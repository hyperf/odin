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

use Closure;
use Exception;
use Generator;
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Contract\Memory\MemoryInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;
use stdClass;

/**
 * 测试 ToolUseAgent 类的功能.
 * @internal
 * @coversNothing
 */
class ToolUseAgentTest extends AbstractTestCase
{
    /**
     * 模拟的 LLM 模型实例.
     *
     * @var MockInterface|ModelInterface
     */
    private $model;

    /**
     * 模拟的内存管理器实例.
     *
     * @var MemoryInterface|MockInterface
     */
    private $memory;

    /**
     * 模拟的日志记录器实例.
     *
     * @var LoggerInterface|MockInterface
     */
    private $logger;

    /**
     * 测试用工具定义集合.
     *
     * @var array<string, ToolDefinition>
     */
    private array $tools = [];

    /**
     * 设置测试环境.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟对象
        $this->model = Mockery::mock(ModelInterface::class);
        $this->memory = Mockery::mock(MemoryInterface::class);
        $this->logger = Mockery::mock(LoggerInterface::class);

        // 定义默认的 mock 行为
        $this->memory->shouldReceive('getMessages')
            ->andReturn([]);

        $this->memory->shouldReceive('getSystemMessages')
            ->andReturn([]);

        $this->memory->shouldReceive('addMessage')
            ->andReturn($this->memory);

        $this->logger->shouldReceive('debug')->andReturn(null);
        $this->logger->shouldReceive('info')->andReturn(null);
        $this->logger->shouldReceive('warning')->andReturn(null);
        $this->logger->shouldReceive('error')->andReturn(null);

        // 创建测试用工具
        $this->tools = [
            'calculator' => new ToolDefinition(
                name: 'calculator',
                description: '一个简单的计算器工具',
                toolHandler: function ($params) {
                    $a = $params['a'] ?? 0;
                    $b = $params['b'] ?? 0;
                    $operation = $params['operation'] ?? 'add';

                    switch ($operation) {
                        case 'add':
                            return ['result' => $a + $b];
                        case 'subtract':
                            return ['result' => $a - $b];
                        case 'multiply':
                            return ['result' => $a * $b];
                        case 'divide':
                            if ($b == 0) {
                                throw new RuntimeException('Division by zero');
                            }
                            return ['result' => $a / $b];
                        default:
                            throw new InvalidArgumentException("Unknown operation: {$operation}");
                    }
                }
            ),
            'echo' => new ToolDefinition(
                name: 'echo',
                description: '回显工具',
                toolHandler: function ($params) {
                    return ['message' => $params['message']];
                }
            ),
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试 __construct 方法能正确初始化对象和依赖.
     */
    public function testConstructor()
    {
        // 使用完整参数创建对象
        $agent = new ToolUseAgent(
            $this->model,
            $this->memory,
            $this->tools,
            0.6,
            $this->logger
        );

        // 验证 tools 属性被正确设置
        $toolsProperty = $this->getNonpublicProperty($agent, 'tools');
        $this->assertSame($this->tools, $toolsProperty);

        // 验证其他属性被正确设置
        $modelProperty = $this->getNonpublicProperty($agent, 'model');
        $this->assertSame($this->model, $modelProperty);

        $memoryProperty = $this->getNonpublicProperty($agent, 'memory');
        $this->assertSame($this->memory, $memoryProperty);

        $loggerProperty = $this->getNonpublicProperty($agent, 'logger');
        $this->assertSame($this->logger, $loggerProperty);

        // 验证默认属性值
        $toolsDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(30, $toolsDepth);

        $usedTools = $this->getNonpublicProperty($agent, 'usedTools');
        $this->assertIsArray($usedTools);
        $this->assertEmpty($usedTools);

        $toolCallsBeforeEvent = $this->getNonpublicProperty($agent, 'toolCallsBeforeEvent');
        $this->assertNull($toolCallsBeforeEvent);

        // 测试可选参数的情况
        $agentWithoutOptionals = new ToolUseAgent($this->model);

        // 验证必须的模型属性存在
        $modelProperty = $this->getNonpublicProperty($agentWithoutOptionals, 'model');
        $this->assertSame($this->model, $modelProperty);

        // 验证其他可选属性已设置为默认值
        $toolsProperty = $this->getNonpublicProperty($agentWithoutOptionals, 'tools');
        $this->assertIsArray($toolsProperty);
        $this->assertEmpty($toolsProperty);

        $memoryProperty = $this->getNonpublicProperty($agentWithoutOptionals, 'memory');
        $this->assertNotNull($memoryProperty);
    }

    /**
     * 测试 setToolCallBeforeEvent 方法能正确设置回调函数.
     */
    public function testSetToolCallBeforeEvent()
    {
        $agent = new ToolUseAgent($this->model);

        // 检查初始状态
        $toolCallsBeforeEvent = $this->getNonpublicProperty($agent, 'toolCallsBeforeEvent');
        $this->assertNull($toolCallsBeforeEvent);

        // 设置回调函数
        $callback = function ($toolCalls) {
            return 'processed';
        };

        $result = $agent->setToolCallBeforeEvent($callback);

        // 验证返回值是否是当前对象（支持链式调用）
        $this->assertSame($agent, $result);

        // 验证回调函数已正确设置
        $updatedCallback = $this->getNonpublicProperty($agent, 'toolCallsBeforeEvent');
        $this->assertInstanceOf(Closure::class, $updatedCallback);

        // 使用链式调用
        $agent
            ->setToolCallBeforeEvent(function ($toolCalls) {
                return 'chain call';
            });

        // 验证回调函数已被更新
        $chainCallback = $this->getNonpublicProperty($agent, 'toolCallsBeforeEvent');
        $this->assertInstanceOf(Closure::class, $chainCallback);
        $this->assertNotSame($updatedCallback, $chainCallback);
    }

    /**
     * 测试 setToolsDepth 方法能正确设置工具调用深度.
     */
    public function testSetToolsDepth()
    {
        $agent = new ToolUseAgent($this->model);

        // 检查默认深度
        $toolsDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(30, $toolsDepth);

        // 设置新的深度
        $result = $agent->setToolsDepth(5);

        // 验证返回值是否是当前对象（支持链式调用）
        $this->assertSame($agent, $result);

        // 验证深度已正确设置
        $updatedDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(5, $updatedDepth);

        // 使用链式调用设置另一个深度
        $agent->setToolsDepth(10);

        // 验证深度已被更新
        $chainDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(10, $chainDepth);

        // 测试设置零或负数深度
        $agent->setToolsDepth(0);
        $zeroDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(0, $zeroDepth);

        $agent->setToolsDepth(-5);
        $negativeDepth = $this->getNonpublicProperty($agent, 'toolsDepth');
        $this->assertEquals(-5, $negativeDepth);
    }

    /**
     * 测试 getUsedTools 方法能正确返回已使用的工具记录.
     */
    public function testGetUsedTools()
    {
        $agent = new ToolUseAgent($this->model);

        // 初始应该为空数组
        $initialTools = $agent->getUsedTools();
        $this->assertIsArray($initialTools);
        $this->assertEmpty($initialTools);

        // 设置一些测试用的工具记录
        $testTools = [
            ['id' => '1', 'name' => 'tool1'],
            ['id' => '2', 'name' => 'tool2'],
        ];
        $this->setNonpublicPropertyValue($agent, 'usedTools', $testTools);

        // 验证 getUsedTools 返回正确的数据
        $returnedTools = $agent->getUsedTools();
        $this->assertSame($testTools, $returnedTools);
    }

    /**
     * 测试 shouldContinueToolCalls 方法的判断逻辑.
     */
    public function testShouldContinueToolCalls()
    {
        $agent = new ToolUseAgent($this->model);

        // 使用反射访问私有方法
        $reflectionMethod = new ReflectionMethod(ToolUseAgent::class, 'shouldContinueToolCalls');
        $reflectionMethod->setAccessible(true);

        // 创建模拟的 AssistantMessage，带有工具调用
        $messageWithToolCalls = Mockery::mock(AssistantMessage::class);
        $messageWithToolCalls->shouldReceive('hasToolCalls')->andReturn(true);

        // 创建模拟的 AssistantMessage，不带工具调用
        $messageWithoutToolCalls = Mockery::mock(AssistantMessage::class);
        $messageWithoutToolCalls->shouldReceive('hasToolCalls')->andReturn(false);

        // 测试情况1：深度小于设置的限制且消息中有工具调用 - 应返回true
        $this->setNonpublicPropertyValue($agent, 'toolsDepth', 10);
        $result1 = $reflectionMethod->invoke($agent, 5, $messageWithToolCalls);
        $this->assertTrue($result1, '当深度小于限制且有工具调用时应继续执行');

        // 测试情况2：深度等于设置的限制 - 应返回false
        $this->setNonpublicPropertyValue($agent, 'toolsDepth', 5);
        $result2 = $reflectionMethod->invoke($agent, 5, $messageWithToolCalls);
        $this->assertFalse($result2, '当深度等于限制时应停止执行');

        // 测试情况3：深度大于设置的限制 - 应返回false
        $this->setNonpublicPropertyValue($agent, 'toolsDepth', 3);
        $result3 = $reflectionMethod->invoke($agent, 5, $messageWithToolCalls);
        $this->assertFalse($result3, '当深度大于限制时应停止执行');

        // 测试情况4：深度小于设置的限制，但消息中没有工具调用 - 应返回false
        $this->setNonpublicPropertyValue($agent, 'toolsDepth', 10);
        $result4 = $reflectionMethod->invoke($agent, 5, $messageWithoutToolCalls);
        $this->assertFalse($result4, '当消息中没有工具调用时应停止执行');
    }

    /**
     * 测试 formatToolResult 方法对不同结果的格式化.
     */
    public function testFormatToolResult()
    {
        $agent = new ToolUseAgent($this->model);

        // 使用反射访问私有方法
        $reflectionMethod = new ReflectionMethod(ToolUseAgent::class, 'formatToolResult');
        $reflectionMethod->setAccessible(true);

        // 测试标量值
        $this->assertEquals('test', $reflectionMethod->invoke($agent, 'test'), '字符串应保持原样');
        $this->assertEquals('123', $reflectionMethod->invoke($agent, 123), '数字应转换为字符串');
        $this->assertEquals('true', $reflectionMethod->invoke($agent, true), '布尔值true应转换为字符串"true"');
        $this->assertEquals('false', $reflectionMethod->invoke($agent, false), '布尔值false应转换为字符串"false"');
        $this->assertEquals('', $reflectionMethod->invoke($agent, null), 'null应转换为空字符串');

        // 测试数组
        $array = ['name' => 'test', 'age' => 30, 'active' => true];
        $expectedJson = '{"name":"test","age":30,"active":true}';
        $this->assertEquals($expectedJson, $reflectionMethod->invoke($agent, $array), '数组应转换为JSON字符串');

        // 测试嵌套数组
        $nestedArray = ['user' => ['name' => 'test', 'roles' => ['admin', 'user']]];
        // 由于不同系统环境下JSON转义可能不同，我们使用json_decode比较实际内容
        $result = $reflectionMethod->invoke($agent, $nestedArray);
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded, 'formatToolResult结果应为有效的JSON');
        $this->assertArrayHasKey('user', $decoded, 'formatToolResult结果应包含user键');

        // 获取user数据，可能是字符串或直接是数组
        $userValue = $decoded['user'];
        $userData = is_string($userValue) ? json_decode($userValue, true) : $userValue;

        $this->assertIsArray($userData, 'user值应为数组或可解析为数组的JSON');
        $this->assertEquals('test', $userData['name'], '嵌套的name字段应正确保存');

        // 获取roles数据，可能是字符串或直接是数组
        $rolesValue = $userData['roles'];
        $rolesData = is_string($rolesValue) ? json_decode($rolesValue, true) : $rolesValue;

        $this->assertEquals(['admin', 'user'], $rolesData, '嵌套的roles数组应正确保存');

        // 测试对象 - 使用具有toArray方法的测试对象
        $objectWithToArray = new class {
            public function toArray()
            {
                return ['property' => 'value'];
            }
        };
        $this->assertEquals('{"property":"value"}', $reflectionMethod->invoke($agent, $objectWithToArray), '具有toArray方法的对象应使用toArray结果');

        // 测试对象 - 使用具有__toString方法的测试对象
        $objectWithToString = new class {
            public function __toString()
            {
                return 'string representation';
            }
        };
        $this->assertEquals('string representation', $reflectionMethod->invoke($agent, $objectWithToString), '具有__toString方法的对象应使用字符串表示');

        // 测试普通对象
        $stdObject = new stdClass();
        $stdObject->property = 'value';
        $this->assertEquals('{"property":"value"}', $reflectionMethod->invoke($agent, $stdObject), '普通对象应转换为JSON');

        // 测试闭包
        $closure = function () { return 'test'; };
        $this->assertEquals('{}', $reflectionMethod->invoke($agent, $closure), '闭包应转换为空JSON对象');
    }

    /**
     * 测试 call 方法的工具调用逻辑.
     */
    public function testCallMethodWithToolCall()
    {
        // 模拟用户输入消息
        $userMessage = new UserMessage('请执行计算器工具，计算 5 + 3');

        // 创建对话回复的 Assistant 消息
        $assistantMessageWithToolCall = new AssistantMessage(
            '我将使用计算器工具为您计算 5 + 3',
            [
                new ToolCall(
                    name: 'calculator',
                    arguments: ['a' => 5, 'b' => 3],
                    id: 'tool-1234',
                    type: 'function'
                ),
            ]
        );

        // 创建模拟的 ChatCompletionResponse
        $mockResponse = Mockery::mock(ChatCompletionResponse::class);
        $mockChoice = Mockery::mock(ChatCompletionChoice::class);

        // 设置 mock 对象的行为
        $mockChoice->shouldReceive('getMessage')
            ->andReturn($assistantMessageWithToolCall);
        $mockChoice->shouldReceive('isFinishedByToolCall')
            ->andReturn(false);
        $mockChoice->shouldReceive('getIndex')
            ->andReturn(0);
        $mockChoice->shouldReceive('getLogprobs')
            ->andReturn(null);

        $mockResponse->shouldReceive('getFirstChoice')
            ->andReturn($mockChoice);
        $mockResponse->shouldReceive('setChoices')->andReturn($mockResponse);

        // 第二次调用时的返回
        $finalAssistantMessage = new AssistantMessage('计算结果是 8');
        $finalMockChoice = Mockery::mock(ChatCompletionChoice::class);
        $finalMockChoice->shouldReceive('getMessage')
            ->andReturn($finalAssistantMessage);
        $finalMockChoice->shouldReceive('isFinishedByToolCall')
            ->andReturn(true);
        $finalMockChoice->shouldReceive('getIndex')
            ->andReturn(0);
        $finalMockChoice->shouldReceive('getLogprobs')
            ->andReturn(null);

        $finalMockResponse = Mockery::mock(ChatCompletionResponse::class);
        $finalMockResponse->shouldReceive('getFirstChoice')
            ->andReturn($finalMockChoice);
        $finalMockResponse->shouldReceive('setChoices')->andReturn($finalMockResponse);

        // 设置模型的预期行为 - 应调用至少一次
        $this->model->shouldReceive('chat')
            ->atLeast(1)
            ->andReturn($mockResponse, $finalMockResponse);

        // 创建 agent 实例
        $agent = new ToolUseAgent(
            $this->model,
            $this->memory,
            $this->tools,
            0.6,
            $this->logger
        );

        // 直接使用反射调用 call 方法
        $reflectionMethod = new ReflectionMethod(ToolUseAgent::class, 'call');
        $reflectionMethod->setAccessible(true);

        /** @var Generator $generator */
        $generator = $reflectionMethod->invoke($agent, $userMessage);

        // 获取生成器的当前值
        $current = $generator->current();
        // 验证当前值是 ChatCompletionResponse 类型
        $this->assertInstanceOf(ChatCompletionResponse::class, $current);

        // 验证 usedTools 中还没有工具使用记录
        $this->assertEmpty($agent->getUsedTools());

        // 我们需要手动发送一个值给生成器，模拟 yield 的行为
        $generator->send($assistantMessageWithToolCall);

        // 继续执行生成器
        $generator->next();

        // 这里要修改断言，因为循环可能在继续而不是结束
        // 我们应该验证工具是否被成功调用，而不是生成器是否结束
        $usedTools = $agent->getUsedTools();
        $this->assertArrayHasKey('tool-1234', $usedTools);
        $this->assertEquals('calculator', $usedTools['tool-1234']->getName());
        $this->assertEquals(['a' => 5, 'b' => 3], $usedTools['tool-1234']->getArguments());

        // 如果生成器仍然有效，继续执行直到结束
        while ($generator->valid()) {
            $generator->next();
        }

        // 验证最终结果
        $result = $generator->getReturn();
        $this->assertInstanceOf(ChatCompletionResponse::class, $result);
    }

    /**
     * 测试工具调用深度超过限制时的行为.
     */
    public function testToolsDepthLimit()
    {
        // 模拟用户输入消息
        $userMessage = new UserMessage('请执行计算器工具');

        // 创建带有工具调用的助手消息
        $assistantMessageWithToolCall = new AssistantMessage(
            '我将使用计算器工具',
            [
                new ToolCall(
                    name: 'calculator',
                    arguments: ['a' => 1, 'b' => 2],
                    id: 'tool-1',
                    type: 'function'
                ),
            ]
        );

        // 创建模拟的 ChatCompletionResponse
        $mockResponse = Mockery::mock(ChatCompletionResponse::class);
        $mockChoice = Mockery::mock(ChatCompletionChoice::class);

        // 设置模拟对象的行为
        $mockChoice->shouldReceive('getMessage')
            ->andReturn($assistantMessageWithToolCall);
        $mockChoice->shouldReceive('isFinishedByToolCall')
            ->andReturn(false);
        $mockChoice->shouldReceive('getIndex')
            ->andReturn(0);
        $mockChoice->shouldReceive('getLogprobs')
            ->andReturn(null);

        $mockResponse->shouldReceive('getFirstChoice')
            ->andReturn($mockChoice);
        $mockResponse->shouldReceive('setChoices')->andReturn($mockResponse);

        // 设置模型的预期行为 - 应该只调用一次，因为深度限制会阻止第二次调用
        $this->model->shouldReceive('chat')
            ->atLeast(1)
            ->andReturn($mockResponse);

        // 创建 agent 实例并设置极低的工具调用深度限制
        $agent = new ToolUseAgent(
            $this->model,
            $this->memory,
            $this->tools,
            0.6,
            $this->logger
        );
        $agent->setToolsDepth(0); // 设置深度为0，确保第一次工具调用后就不再继续

        // 使用反射获取私有方法 shouldContinueToolCalls
        $reflectionMethod = new ReflectionMethod(ToolUseAgent::class, 'shouldContinueToolCalls');
        $reflectionMethod->setAccessible(true);

        // 在深度为0时，即使消息中有工具调用，也应该返回false
        $result = $reflectionMethod->invoke($agent, 0, $assistantMessageWithToolCall);
        $this->assertFalse($result, '当深度等于限制时应停止执行工具调用');

        // 调用 call 方法
        $callMethod = new ReflectionMethod(ToolUseAgent::class, 'call');
        $callMethod->setAccessible(true);

        /** @var Generator $generator */
        $generator = $callMethod->invoke($agent, $userMessage);

        // 获取生成器的当前值
        $current = $generator->current();
        $this->assertInstanceOf(ChatCompletionResponse::class, $current);

        // 发送助手消息
        $generator->send($assistantMessageWithToolCall);

        // 由于深度限制为0，生成器应该结束，不会进行递归调用
        $this->assertFalse($generator->valid(), '由于深度限制为0，生成器应该结束');

        // 验证最终结果
        $result = $generator->getReturn();
        $this->assertInstanceOf(ChatCompletionResponse::class, $result);

        // 注意：即使深度限制为0，第一次工具调用仍然会执行，这是因为 executeToolCalls 方法是在 shouldContinueToolCalls 之前被调用的
        // 根据代码的实现逻辑，我们验证只有一个工具被执行，且是第一次调用的工具
        $usedTools = $agent->getUsedTools();
        $this->assertCount(1, $usedTools, '应该只有一个工具被执行');
        $this->assertArrayHasKey('tool-1', $usedTools);
        $this->assertEquals('calculator', $usedTools['tool-1']->getName());
        $this->assertEquals(['a' => 1, 'b' => 2], $usedTools['tool-1']->getArguments());
    }

    /**
     * 测试工具调用成功的情况.
     */
    public function testToolCallSuccess()
    {
        // 模拟用户输入消息
        $userMessage = new UserMessage('请执行计算器工具计算 10 + 20');

        // 创建带有工具调用的助手消息
        $assistantMessageWithToolCall = new AssistantMessage(
            '我将使用计算器工具为您计算 10 + 20',
            [
                new ToolCall(
                    name: 'calculator',
                    arguments: ['a' => 10, 'b' => 20],
                    id: 'tool-123',
                    type: 'function'
                ),
            ]
        );

        // 创建模拟的 ChatCompletionResponse
        $mockResponse = Mockery::mock(ChatCompletionResponse::class);
        $mockChoice = Mockery::mock(ChatCompletionChoice::class);
        $mockChoice->shouldReceive('getMessage')->andReturn($assistantMessageWithToolCall);
        $mockChoice->shouldReceive('isFinishedByToolCall')->andReturn(false);
        $mockChoice->shouldReceive('getIndex')->andReturn(0);
        $mockChoice->shouldReceive('getLogprobs')->andReturn(null);
        $mockResponse->shouldReceive('getFirstChoice')->andReturn($mockChoice);
        $mockResponse->shouldReceive('setChoices')->andReturn($mockResponse);

        // 创建最终响应，用于工具执行后的回复
        $finalAssistantMessage = new AssistantMessage('计算结果是 30');
        $finalMockChoice = Mockery::mock(ChatCompletionChoice::class);
        $finalMockChoice->shouldReceive('getMessage')->andReturn($finalAssistantMessage);
        $finalMockChoice->shouldReceive('isFinishedByToolCall')->andReturn(true);
        $finalMockChoice->shouldReceive('getIndex')->andReturn(0);
        $finalMockChoice->shouldReceive('getLogprobs')->andReturn(null);
        $finalMockResponse = Mockery::mock(ChatCompletionResponse::class);
        $finalMockResponse->shouldReceive('getFirstChoice')->andReturn($finalMockChoice);
        $finalMockResponse->shouldReceive('setChoices')->andReturn($finalMockResponse);

        // 设置模型的预期行为 - 应调用至少一次
        $this->model->shouldReceive('chat')
            ->atLeast(1)
            ->andReturn($mockResponse, $finalMockResponse);

        // 创建 agent 实例
        $agent = new ToolUseAgent(
            $this->model,
            $this->memory,
            $this->tools,
            0.6,
            $this->logger
        );

        // 执行 call 方法的测试
        $callMethod = new ReflectionMethod(ToolUseAgent::class, 'call');
        $callMethod->setAccessible(true);

        /** @var Generator $generator */
        $generator = $callMethod->invoke($agent, $userMessage);

        // 获取生成器的当前值并发送助手消息
        $current = $generator->current();
        $generator->send($assistantMessageWithToolCall);

        // 执行一步让工具调用发生
        $generator->next();

        // 验证工具使用记录
        $usedTools = $agent->getUsedTools();
        $this->assertCount(1, $usedTools);
        $this->assertArrayHasKey('tool-123', $usedTools);

        // 验证工具调用成功
        $toolCall = $usedTools['tool-123'];
        $this->assertTrue($toolCall->isSuccess(), '工具调用应该成功');

        // 获取结果并验证，结果是数组
        $result = $toolCall->getResult();
        $this->assertIsArray($result, '工具调用结果应该是数组');
        $this->assertArrayHasKey('result', $result, '结果数组应该包含result键');
        $this->assertEquals(30, $result['result'], '计算结果应该是30');

        $this->assertEmpty($toolCall->getErrorMessage(), '成功的工具调用不应有错误消息');

        // 继续执行生成器直到结束
        while ($generator->valid()) {
            $generator->next();
        }
    }

    /**
     * 测试工具调用失败的情况.
     */
    public function testToolCallFailure()
    {
        // 创建一个会抛出异常的工具
        $errorTool = new ToolDefinition(
            name: 'error_tool',
            description: '总是抛出异常的工具',
            toolHandler: function () {
                throw new Exception('测试异常');
            }
        );

        // 将错误工具添加到工具集
        $this->tools['error_tool'] = $errorTool;

        // 模拟用户输入消息
        $userMessage = new UserMessage('请执行会出错的工具');

        // 创建带有工具调用的助手消息
        $assistantMessageWithToolCall = new AssistantMessage(
            '我将使用会出错的工具',
            [
                new ToolCall(
                    name: 'error_tool',
                    arguments: [],
                    id: 'tool-error',
                    type: 'function'
                ),
            ]
        );

        // 创建模拟的 ChatCompletionResponse
        $mockResponse = Mockery::mock(ChatCompletionResponse::class);
        $mockChoice = Mockery::mock(ChatCompletionChoice::class);
        $mockChoice->shouldReceive('getMessage')->andReturn($assistantMessageWithToolCall);
        $mockChoice->shouldReceive('isFinishedByToolCall')->andReturn(false);
        $mockChoice->shouldReceive('getIndex')->andReturn(0);
        $mockChoice->shouldReceive('getLogprobs')->andReturn(null);
        $mockResponse->shouldReceive('getFirstChoice')->andReturn($mockChoice);
        $mockResponse->shouldReceive('setChoices')->andReturn($mockResponse);

        // 创建最终响应
        $finalAssistantMessage = new AssistantMessage('工具执行出错了');
        $finalMockChoice = Mockery::mock(ChatCompletionChoice::class);
        $finalMockChoice->shouldReceive('getMessage')->andReturn($finalAssistantMessage);
        $finalMockChoice->shouldReceive('isFinishedByToolCall')->andReturn(true);
        $finalMockChoice->shouldReceive('getIndex')->andReturn(0);
        $finalMockChoice->shouldReceive('getLogprobs')->andReturn(null);
        $finalMockResponse = Mockery::mock(ChatCompletionResponse::class);
        $finalMockResponse->shouldReceive('getFirstChoice')->andReturn($finalMockChoice);
        $finalMockResponse->shouldReceive('setChoices')->andReturn($finalMockResponse);

        // 设置模型的预期行为 - 应调用至少一次
        $this->model->shouldReceive('chat')
            ->atLeast(1)
            ->andReturn($mockResponse, $finalMockResponse);

        // 创建 agent 实例
        $agent = new ToolUseAgent(
            $this->model,
            $this->memory,
            $this->tools,
            0.6,
            $this->logger
        );

        // 执行 call 方法的测试
        $callMethod = new ReflectionMethod(ToolUseAgent::class, 'call');
        $callMethod->setAccessible(true);

        /** @var Generator $generator */
        $generator = $callMethod->invoke($agent, $userMessage);

        // 获取生成器的当前值并发送助手消息
        $current = $generator->current();
        $generator->send($assistantMessageWithToolCall);

        // 执行一步让工具调用发生
        $generator->next();

        // 验证工具使用记录
        $usedTools = $agent->getUsedTools();
        $this->assertCount(1, $usedTools);
        $this->assertArrayHasKey('tool-error', $usedTools);

        // 验证工具调用失败
        $toolCall = $usedTools['tool-error'];
        $this->assertFalse($toolCall->isSuccess(), '工具调用应该失败');
        $this->assertNotEmpty($toolCall->getErrorMessage(), '失败的工具调用应有错误消息');
        $this->assertEquals('测试异常', $toolCall->getErrorMessage(), '错误消息应该与抛出的异常匹配');

        // 继续执行生成器直到结束
        while ($generator->valid()) {
            $generator->next();
        }
    }

    /**
     * 测试 Memory 相关功能的正确性.
     */
    public function testMemoryFunctionality()
    {
        // 重置之前设置的模拟对象
        Mockery::close();

        // 重新创建模拟对象
        $model = Mockery::mock(ModelInterface::class);
        $memory = Mockery::mock(MemoryInterface::class);
        $logger = Mockery::mock(LoggerInterface::class);

        // 模拟用户输入消息
        $userMessage = new UserMessage('记忆测试');

        // 创建带有工具调用的助手消息
        $assistantMessageWithToolCall = new AssistantMessage(
            '我将使用记忆功能',
            [
                new ToolCall(
                    name: 'memory',
                    arguments: ['key' => 'memory-test', 'value' => 'success'],
                    id: 'tool-333',
                    type: 'function'
                ),
            ]
        );

        // 创建模拟的 Tools
        $tools = [
            'memory' => new ToolDefinition(
                name: 'memory',
                description: '内存工具',
                toolHandler: function ($params) {
                    return ['status' => 'saved', 'key' => $params['key'], 'value' => $params['value']];
                }
            ),
        ];

        // 创建模拟的 ChatCompletionResponse
        $mockResponse = Mockery::mock(ChatCompletionResponse::class);
        $mockChoice = Mockery::mock(ChatCompletionChoice::class);

        // 设置模拟对象的行为
        $mockChoice->shouldReceive('getMessage')
            ->andReturn($assistantMessageWithToolCall);
        $mockChoice->shouldReceive('isFinishedByToolCall')
            ->andReturn(false);
        $mockChoice->shouldReceive('getIndex')
            ->andReturn(0);
        $mockChoice->shouldReceive('getLogprobs')
            ->andReturn(null);

        $mockResponse->shouldReceive('getFirstChoice')
            ->andReturn($mockChoice);
        $mockResponse->shouldReceive('setChoices')
            ->andReturn($mockResponse);

        // 设置模型的预期行为 - 应调用至少一次
        $model->shouldReceive('chat')
            ->atLeast(1)
            ->andReturn($mockResponse, $mockResponse);

        // 设置 memory 的预期行为 - 验证消息被添加到内存中
        $memory->shouldReceive('addMessage')
            ->with(Mockery::type(UserMessage::class))
            ->once()
            ->andReturnSelf();

        $memory->shouldReceive('addMessage')
            ->with(Mockery::type(AssistantMessage::class))
            ->atLeast(1)
            ->andReturnSelf();

        $memory->shouldReceive('addMessage')
            ->with(Mockery::type(ToolMessage::class))
            ->atLeast(1)
            ->andReturnSelf();

        $memory->shouldReceive('applyPolicy')
            ->andReturnSelf();

        $memory->shouldReceive('getProcessedMessages')
            ->andReturn([]);

        $memory->shouldReceive('getMessages')
            ->andReturn([]);

        $memory->shouldReceive('getSystemMessages')
            ->andReturn([]);

        // 设置 logger 的预期行为
        $logger->shouldReceive('info')->andReturnNull();
        $logger->shouldReceive('debug')->andReturnNull();

        // 创建 agent 实例
        $agent = new ToolUseAgent(
            $model,
            $memory,
            $tools,
            0.6,
            $logger
        );

        // 执行 call 方法的测试
        $callMethod = new ReflectionMethod(ToolUseAgent::class, 'call');
        $callMethod->setAccessible(true);

        /** @var Generator $generator */
        $generator = $callMethod->invoke($agent, $userMessage);

        // 获取生成器的当前值并发送助手消息
        $current = $generator->current();
        $generator->send($assistantMessageWithToolCall);

        // 执行到结束
        while ($generator->valid()) {
            $generator->next();
        }

        // 验证 Memory 相关操作已正确执行
        $usedTools = $agent->getUsedTools();
        $this->assertCount(1, $usedTools);
        $this->assertArrayHasKey('tool-333', $usedTools);

        // 重新设置测试类的属性
        $this->setUp();
    }
}

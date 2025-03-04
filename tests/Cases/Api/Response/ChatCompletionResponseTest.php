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

namespace HyperfTest\Odin\Cases\Api\Response;

use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Api\Response\Usage;
use Hyperf\Odin\Message\AssistantMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Response\ChatCompletionResponse
 */
class ChatCompletionResponseTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试从有效JSON响应构造.
     */
    public function testConstructFromValidJsonResponse()
    {
        // 创建一个模拟的HTTP响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode([
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'PHP是一种流行的服务器端脚本语言。',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ]));

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建ChatCompletionResponse实例
        $chatResponse = new ChatCompletionResponse($response);

        // 验证基本属性
        $this->assertTrue($chatResponse->isSuccess());
        $this->assertEquals('chatcmpl-123', $chatResponse->getId());
        $this->assertEquals('chat.completion', $chatResponse->getObject());
        $this->assertEquals(1677652288, $chatResponse->getCreated());
        $this->assertEquals('gpt-3.5-turbo', $chatResponse->getModel());

        // 验证choices
        $choices = $chatResponse->getChoices();
        $this->assertCount(1, $choices);
        $this->assertInstanceOf(ChatCompletionChoice::class, $choices[0]);
        $this->assertEquals(0, $choices[0]->getIndex());
        $this->assertEquals('stop', $choices[0]->getFinishReason());
        $this->assertInstanceOf(AssistantMessage::class, $choices[0]->getMessage());
        $this->assertEquals('PHP是一种流行的服务器端脚本语言。', $choices[0]->getMessage()->getContent());

        // 验证usage
        $usage = $chatResponse->getUsage();
        $this->assertInstanceOf(Usage::class, $usage);
        $this->assertEquals(10, $usage->getPromptTokens());
        $this->assertEquals(20, $usage->getCompletionTokens());
        $this->assertEquals(30, $usage->getTotalTokens());

        // 验证__toString
        $this->assertEquals('PHP是一种流行的服务器端脚本语言。', (string) $chatResponse);
    }

    /**
     * 测试从包含工具调用的JSON响应构造.
     */
    public function testConstructFromResponseWithToolCalls()
    {
        // 创建一个模拟的HTTP响应，包含工具调用
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode([
            'id' => 'chatcmpl-456',
            'object' => 'chat.completion',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => '我将为您查询北京的天气。',
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location":"北京","unit":"celsius"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 15,
                'completion_tokens' => 25,
                'total_tokens' => 40,
            ],
        ]));

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建ChatCompletionResponse实例
        $chatResponse = new ChatCompletionResponse($response);

        // 验证基本属性
        $this->assertTrue($chatResponse->isSuccess());
        $this->assertEquals('chatcmpl-456', $chatResponse->getId());

        // 验证choices和工具调用
        $choices = $chatResponse->getChoices();
        $this->assertCount(1, $choices);
        $this->assertEquals('tool_calls', $choices[0]->getFinishReason());
        $this->assertTrue($choices[0]->isFinishedByToolCall());

        // 验证消息中的工具调用
        $message = $choices[0]->getMessage();
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertEquals('我将为您查询北京的天气。', $message->getContent());

        // 判断工具调用
        $this->assertArrayHasKey('tool_calls', $message->toArray());
        /** @var AssistantMessage $message */
        /** @var ToolCall[] $toolCalls */
        $toolCalls = $message->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertEquals('call_123', $toolCalls[0]->getId());
        $this->assertEquals('function', $toolCalls[0]->getType());
        $this->assertEquals('get_weather', $toolCalls[0]->getName());

        // 验证工具调用参数
        $arguments = $toolCalls[0]->getArguments();
        $this->assertIsArray($arguments);
        $this->assertEquals('北京', $arguments['location']);
        $this->assertEquals('celsius', $arguments['unit']);
    }

    /**
     * 测试getContent/getMessage方法.
     */
    public function testGetContentAndMessage()
    {
        // 创建一个简单的模拟响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode([
            'id' => 'chatcmpl-789',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => '这是一个测试回复。',
                    ],
                ],
            ],
        ]));

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建ChatCompletionResponse实例
        $chatResponse = new ChatCompletionResponse($response);

        // 测试__toString方法，它应该返回第一个选择的消息内容
        $this->assertEquals('这是一个测试回复。', (string) $chatResponse);

        // 获取第一个选择
        $firstChoice = $chatResponse->getFirstChoice();
        $this->assertInstanceOf(ChatCompletionChoice::class, $firstChoice);

        // 通过第一个选择获取消息内容
        $this->assertEquals('这是一个测试回复。', $firstChoice->getMessage()->getContent());
    }

    /**
     * 测试从不成功的HTTP响应构造.
     */
    public function testConstructFromUnsuccessfulResponse()
    {
        // 创建一个错误的HTTP响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ]));

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(401); // 错误状态码
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建ChatCompletionResponse实例
        $chatResponse = new ChatCompletionResponse($response);

        // 验证响应不成功
        $this->assertFalse($chatResponse->isSuccess());

        // 检查响应内容是否包含错误信息
        $content = $chatResponse->getContent();
        $this->assertNotNull($content);
        $this->assertStringContainsString('Invalid API key', $content);
    }

    /**
     * 测试setters方法.
     */
    public function testSetterMethods()
    {
        // 创建一个基本的响应对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        $chatResponse = new ChatCompletionResponse($response);

        // 测试setter方法
        $chatResponse->setId('custom-id');
        $this->assertEquals('custom-id', $chatResponse->getId());

        $chatResponse->setObject('custom-object');
        $this->assertEquals('custom-object', $chatResponse->getObject());

        $chatResponse->setCreated(12345);
        $this->assertEquals(12345, $chatResponse->getCreated());

        $chatResponse->setModel('custom-model');
        $this->assertEquals('custom-model', $chatResponse->getModel());

        // 测试设置字符串格式的created
        $chatResponse->setCreated('67890');
        $this->assertEquals(67890, $chatResponse->getCreated());
        $this->assertIsInt($chatResponse->getCreated());

        // 测试设置choices
        /** @var ChatCompletionChoice|MockInterface $choice */
        $choice = Mockery::mock(ChatCompletionChoice::class);
        $chatResponse->setChoices([$choice]);
        $this->assertCount(1, $chatResponse->getChoices());
        $this->assertSame($choice, $chatResponse->getChoices()[0]);

        // 测试设置usage
        /** @var MockInterface|Usage $usage */
        $usage = Mockery::mock(Usage::class);
        $chatResponse->setUsage($usage);
        $this->assertSame($usage, $chatResponse->getUsage());
    }
}

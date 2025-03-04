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
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Api\Transport\SSEEvent;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Response\ChatCompletionStreamResponse
 */
class ChatCompletionStreamResponseTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        /** @var LoggerInterface|MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        // 创建ChatCompletionStreamResponse实例
        $streamResponse = new ChatCompletionStreamResponse($response, $logger);

        // 验证基本构造成功
        $this->assertInstanceOf(ChatCompletionStreamResponse::class, $streamResponse);
        $this->assertTrue($streamResponse->isSuccess());
    }

    /**
     * 测试使用SSEClient处理流数据.
     */
    public function testStreamIteratorWithSSEClient()
    {
        // 创建模拟的HTTP响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建模拟SSEClient
        /** @var MockInterface|SSEClient $sseClient */
        $sseClient = Mockery::mock(SSEClient::class);

        // 创建模拟SSE事件
        /** @var MockInterface|SSEEvent $event1 */
        $event1 = Mockery::mock(SSEEvent::class);
        $event1->shouldReceive('getEvent')->andReturn('message');
        $event1->shouldReceive('getData')->andReturn([
            'id' => 'chatcmpl-abc123-1',
            'object' => 'chat.completion.chunk',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'role' => 'assistant',
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);

        /** @var MockInterface|SSEEvent $event2 */
        $event2 = Mockery::mock(SSEEvent::class);
        $event2->shouldReceive('getEvent')->andReturn('message');
        $event2->shouldReceive('getData')->andReturn([
            'id' => 'chatcmpl-abc123-2',
            'object' => 'chat.completion.chunk',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'content' => '你好',
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);

        /** @var MockInterface|SSEEvent $event3 */
        $event3 = Mockery::mock(SSEEvent::class);
        $event3->shouldReceive('getEvent')->andReturn('message');
        $event3->shouldReceive('getData')->andReturn([
            'id' => 'chatcmpl-abc123-3',
            'object' => 'chat.completion.chunk',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'content' => '，',
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);

        /** @var MockInterface|SSEEvent $event4 */
        $event4 = Mockery::mock(SSEEvent::class);
        $event4->shouldReceive('getEvent')->andReturn('message');
        $event4->shouldReceive('getData')->andReturn([
            'id' => 'chatcmpl-abc123-4',
            'object' => 'chat.completion.chunk',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [
                        'content' => '世界',
                    ],
                    'finish_reason' => null,
                ],
            ],
        ]);

        /** @var MockInterface|SSEEvent $event5 */
        $event5 = Mockery::mock(SSEEvent::class);
        $event5->shouldReceive('getEvent')->andReturn('message');
        $event5->shouldReceive('getData')->andReturn([
            'id' => 'chatcmpl-abc123-5',
            'object' => 'chat.completion.chunk',
            'created' => 1677652288,
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'delta' => [],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);

        /** @var MockInterface|SSEEvent $eventDone */
        $eventDone = Mockery::mock(SSEEvent::class);
        $eventDone->shouldReceive('getData')->andReturn('[DONE]');

        // 创建模拟迭代器
        // @phpstan-ignore-next-line
        $sseClient->shouldReceive('getIterator')
            ->once()
            ->andReturn((function () use ($event1, $event2, $event3, $event4, $event5, $eventDone) {
                yield $event1;
                yield $event2;
                yield $event3;
                yield $event4;
                yield $event5;
                yield $eventDone;
            })());

        // 创建StreamResponse
        $streamResponse = new ChatCompletionStreamResponse($response, null, $sseClient);

        // 获取迭代器
        $iterator = $streamResponse->getStreamIterator();

        // 收集所有的chunks
        $chunks = [];
        foreach ($iterator as $chunk) {
            $chunks[] = $chunk;
        }

        // 验证结果
        $this->assertCount(5, $chunks);
        $this->assertInstanceOf(ChatCompletionChoice::class, $chunks[0]);

        // 检查ID和模型是否被正确设置
        $this->assertEquals('chatcmpl-abc123-5', $streamResponse->getId());
        $this->assertEquals('chat.completion.chunk', $streamResponse->getObject());
        $this->assertEquals(1677652288, $streamResponse->getCreated());
        $this->assertEquals('gpt-3.5-turbo', $streamResponse->getModel());

        // 验证最后一个选择的完成原因
        $this->assertEquals('stop', $chunks[4]->getFinishReason());
    }

    /**
     * 测试传统方式处理流数据.
     */
    public function testStreamIteratorWithLegacyMethod()
    {
        // 创建模拟流内容：每一行是一个单独的数据事件
        $streamContent = implode("\n", [
            'data: ' . json_encode([
                'id' => 'chatcmpl-xyz789-1',
                'object' => 'chat.completion.chunk',
                'created' => 1677652288,
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'role' => 'assistant',
                        ],
                        'finish_reason' => null,
                    ],
                ],
            ]),
            'data: ' . json_encode([
                'id' => 'chatcmpl-xyz789-2',
                'object' => 'chat.completion.chunk',
                'created' => 1677652288,
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'content' => '这是',
                        ],
                        'finish_reason' => null,
                    ],
                ],
            ]),
            'data: ' . json_encode([
                'id' => 'chatcmpl-xyz789-3',
                'object' => 'chat.completion.chunk',
                'created' => 1677652288,
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'content' => '传统',
                        ],
                        'finish_reason' => null,
                    ],
                ],
            ]),
            'data: ' . json_encode([
                'id' => 'chatcmpl-xyz789-4',
                'object' => 'chat.completion.chunk',
                'created' => 1677652288,
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'content' => '方法',
                        ],
                        'finish_reason' => null,
                    ],
                ],
            ]),
            'data: ' . json_encode([
                'id' => 'chatcmpl-xyz789-5',
                'object' => 'chat.completion.chunk',
                'created' => 1677652288,
                'model' => 'gpt-3.5-turbo',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [],
                        'finish_reason' => 'stop',
                    ],
                ],
            ]),
            'data: [DONE]',
        ]);

        // 创建模拟流对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        // 模拟流读取行为
        $stream->shouldReceive('eof')->andReturn(false, false, true);
        // @phpstan-ignore-next-line
        $stream->shouldReceive('read')->with(4096)->andReturn($streamContent, '');

        // 创建模拟响应
        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建StreamResponse，不提供SSEClient
        $streamResponse = new ChatCompletionStreamResponse($response);

        // 获取迭代器
        $iterator = $streamResponse->getStreamIterator();

        // 收集所有的chunks
        $chunks = [];
        foreach ($iterator as $chunk) {
            $chunks[] = $chunk;
        }

        // 验证结果
        $this->assertGreaterThan(0, count($chunks));
        $this->assertInstanceOf(ChatCompletionChoice::class, $chunks[0]);

        // 检查ID和模型是否被正确设置
        $this->assertEquals('chatcmpl-xyz789-5', $streamResponse->getId());
        $this->assertEquals('chat.completion.chunk', $streamResponse->getObject());
        $this->assertEquals(1677652288, $streamResponse->getCreated());
        $this->assertEquals('gpt-3.5-turbo', $streamResponse->getModel());
    }

    /**
     * 测试__toString方法.
     */
    public function testToString()
    {
        // 创建模拟响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建StreamResponse
        $streamResponse = new ChatCompletionStreamResponse($response);

        // 验证__toString方法
        $this->assertEquals('Stream Response', (string) $streamResponse);
    }

    /**
     * 测试setter方法.
     */
    public function testSetterMethods()
    {
        // 创建模拟响应
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建StreamResponse
        $streamResponse = new ChatCompletionStreamResponse($response);

        // 测试setter方法
        $streamResponse->setId('test-id');
        $this->assertEquals('test-id', $streamResponse->getId());

        $streamResponse->setObject('test-object');
        $this->assertEquals('test-object', $streamResponse->getObject());

        $streamResponse->setCreated(12345);
        $this->assertEquals(12345, $streamResponse->getCreated());

        $streamResponse->setModel('test-model');
        $this->assertEquals('test-model', $streamResponse->getModel());

        // 测试字符串转整数
        $streamResponse->setCreated('67890');
        $this->assertEquals(67890, $streamResponse->getCreated());
        $this->assertIsInt($streamResponse->getCreated());

        // 测试设置choices
        /** @var ChatCompletionChoice|MockInterface $choice */
        $choice = Mockery::mock(ChatCompletionChoice::class);
        $streamResponse->setChoices([$choice]);
        $this->assertSame([$choice], $streamResponse->getChoices());
    }
}

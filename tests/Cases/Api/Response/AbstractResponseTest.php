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

use Hyperf\Odin\Api\Response\AbstractResponse;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Response\AbstractResponse
 */
class AbstractResponseTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试基本构造函数和成功状态
     */
    public function testConstructorAndSuccessStatus()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"test": "data"}');

        // 创建成功的响应
        /** @var MockInterface|ResponseInterface $successResponse */
        $successResponse = Mockery::mock(ResponseInterface::class);
        $successResponse->shouldReceive('getStatusCode')->andReturn(200);
        $successResponse->shouldReceive('getBody')->andReturn($stream);

        // 创建失败的响应
        /** @var MockInterface|ResponseInterface $failureResponse */
        $failureResponse = Mockery::mock(ResponseInterface::class);
        $failureResponse->shouldReceive('getStatusCode')->andReturn(400);
        $failureResponse->shouldReceive('getBody')->andReturn($stream);

        // 创建测试用的具体响应类
        $successResponseObj = new ConcreteResponse($successResponse);
        $failureResponseObj = new ConcreteResponse($failureResponse);

        // 验证成功状态
        $this->assertTrue($successResponseObj->isSuccess());
        $this->assertFalse($failureResponseObj->isSuccess());
    }

    /**
     * 测试内容解析.
     */
    public function testContentParsing()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"test": "data"}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建测试用的具体响应类
        $responseObj = new ConcreteResponse($response);

        // 验证内容解析
        $this->assertEquals('{"test": "data"}', $responseObj->getContent());
        $this->assertEquals('data', $responseObj->getTestValue());
    }

    /**
     * 测试设置内容.
     */
    public function testSetContent()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建测试用的具体响应类
        $responseObj = new ConcreteResponse($response);

        // 设置新内容
        $responseObj->setContent('{"test": "new data"}');

        // 验证内容更新
        $this->assertEquals('{"test": "new data"}', $responseObj->getContent());
        $this->assertEquals('new data', $responseObj->getTestValue());
    }

    /**
     * 测试无效JSON处理.
     */
    public function testInvalidJsonHandling()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('invalid json');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建测试用的具体响应类
        $responseObj = new ConcreteResponse($response);

        // 验证内容
        $this->assertEquals('invalid json', $responseObj->getContent());
        $this->assertNull($responseObj->getTestValue());
    }

    /**
     * 测试使用日志记录器.
     */
    public function testWithLogger()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"test": "data"}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        /** @var LoggerInterface|MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        // @phpstan-ignore-next-line
        $logger->shouldReceive('debug')->once()->with('Content parsed', Mockery::any());

        // 创建测试用的具体响应类，带日志记录器
        $responseObj = new ConcreteResponse($response, $logger);

        // 验证内容解析
        $this->assertEquals('{"test": "data"}', $responseObj->getContent());
        $this->assertEquals('data', $responseObj->getTestValue());
    }

    /**
     * 测试获取原始响应.
     */
    public function testGetOriginResponse()
    {
        // 创建模拟对象
        /** @var MockInterface|StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn('{"test": "data"}');

        /** @var MockInterface|ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        // 创建测试用的具体响应类
        $responseObj = new ConcreteResponse($response);

        // 验证原始响应
        $this->assertSame($response, $responseObj->getOriginResponse());
    }
}

/**
 * 用于测试的具体响应类.
 */
class ConcreteResponse extends AbstractResponse
{
    protected ?string $testValue = null;

    public function getTestValue(): ?string
    {
        return $this->testValue;
    }

    protected function parseContent(): static
    {
        if (empty($this->content)) {
            $this->content = $this->originResponse->getBody()->getContents();
        }

        try {
            $data = json_decode($this->content, true);
            if (is_array($data) && isset($data['test'])) {
                $this->testValue = $data['test'];
                $this->logger?->debug('Content parsed', ['test' => $this->testValue]);
            }
        } catch (Throwable $e) {
            // 无效JSON处理
        }

        return $this;
    }
}

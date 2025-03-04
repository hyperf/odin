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

use Hyperf\Odin\Api\Response\Embedding;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Api\Response\Usage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Response\EmbeddingResponse
 */
class EmbeddingResponseTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试setter方法.
     */
    public function testSetterMethods()
    {
        // 创建嵌入响应对象，不使用构造函数解析内容
        $embeddingResponse = $this->createResponseWithoutParsing();

        // 测试setObject
        $embeddingResponse->setObject('custom-object');
        $this->assertEquals('custom-object', $embeddingResponse->getObject());

        // 测试setModel
        $embeddingResponse->setModel('custom-model');
        $this->assertEquals('custom-model', $embeddingResponse->getModel());

        // 测试setUsage
        $usage = new Usage(10, 10, 0);
        $embeddingResponse->setUsage($usage);
        $this->assertSame($usage, $embeddingResponse->getUsage());

        // 测试setData
        $embeddingData = [
            [
                'object' => 'embedding',
                'embedding' => [0.7, 0.8, 0.9],
                'index' => 2,
            ],
        ];
        $embeddingResponse->setData($embeddingData);

        // 验证数据被正确解析
        $embeddings = $embeddingResponse->getData();
        $this->assertCount(1, $embeddings);
        $this->assertInstanceOf(Embedding::class, $embeddings[0]);
        $this->assertEquals([0.7, 0.8, 0.9], $embeddings[0]->getEmbedding());
        $this->assertEquals(2, $embeddings[0]->getIndex());
    }

    /**
     * 创建一个不解析内容的响应对象，用于测试setter方法.
     */
    private function createResponseWithoutParsing(): EmbeddingResponse
    {
        // 模拟HTTP响应和日志接口
        /** @var MockInterface&ResponseInterface $response */
        $response = Mockery::mock(ResponseInterface::class);
        /** @var MockInterface&StreamInterface $stream */
        $stream = Mockery::mock(StreamInterface::class);

        // 设置模拟方法 - 返回空响应
        $response->shouldReceive('getBody')->andReturn($stream);
        $stream->shouldReceive('__toString')->andReturn('{}');

        // 使用反射创建对象但不调用构造函数
        $reflection = new ReflectionClass(EmbeddingResponse::class);
        /** @var EmbeddingResponse $embeddingResponse */
        $embeddingResponse = $reflection->newInstanceWithoutConstructor();

        // 手动设置content属性
        $contentProperty = $reflection->getProperty('content');
        $contentProperty->setAccessible(true);
        $contentProperty->setValue($embeddingResponse, '{}');

        return $embeddingResponse;
    }
}

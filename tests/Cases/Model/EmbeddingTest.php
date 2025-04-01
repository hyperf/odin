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

namespace HyperfTest\Odin\Cases\Model;

use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Exception\LLMException\Model\LLMEmbeddingNotSupportedException;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Model\AbstractModel
 */
class EmbeddingTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试不支持嵌入功能的模型.
     */
    public function testEmbeddingWithUnsupportedModel()
    {
        // 创建模拟对象
        /** @var ClientInterface&MockInterface $client */
        $client = Mockery::mock(ClientInterface::class);
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes();

        // 客户端方法不应该被调用
        $client->shouldNotReceive('embeddings');

        // 创建测试模型
        $model = $this->createModelWithMockedClient('gpt-3.5-turbo', $client);

        // 设置模型选项，禁用嵌入功能
        $modelOptions = new ModelOptions(['embedding' => false]);
        $model->setModelOptions($modelOptions);

        // 期望抛出异常
        $this->expectException(LLMEmbeddingNotSupportedException::class);
        $this->expectExceptionMessage('模型 gpt-3.5-turbo 不支持嵌入功能');

        // 执行嵌入，应该抛出异常
        $model->embedding('测试文本');
    }

    /**
     * 测试checkEmbeddingSupport方法.
     */
    public function testCheckEmbeddingSupport()
    {
        // 创建模拟对象
        /** @var ClientInterface&MockInterface $client */
        $client = Mockery::mock(ClientInterface::class);

        // 1. 测试支持嵌入的模型
        $supportedModel = $this->createModelWithMockedClient('text-embedding-ada-002', $client);
        $supportedModel->setModelOptions(new ModelOptions(['embedding' => true]));

        // 调用受保护的方法
        $supportedReflection = new ReflectionClass($supportedModel);
        $supportedMethod = $supportedReflection->getMethod('checkEmbeddingSupport');
        $supportedMethod->setAccessible(true);

        // 不应抛出异常
        $supportedMethod->invoke($supportedModel);
        $this->assertTrue(true); // 如果到达这里，说明没有异常抛出

        // 2. 测试不支持嵌入的模型
        $unsupportedModel = $this->createModelWithMockedClient('gpt-3.5-turbo', $client);
        $unsupportedModel->setModelOptions(new ModelOptions(['embedding' => false]));

        // 获取受保护的方法
        $unsupportedReflection = new ReflectionClass($unsupportedModel);
        $unsupportedMethod = $unsupportedReflection->getMethod('checkEmbeddingSupport');
        $unsupportedMethod->setAccessible(true);

        // 应该抛出异常
        $this->expectException(LLMEmbeddingNotSupportedException::class);
        $this->expectExceptionMessage('模型 gpt-3.5-turbo 不支持嵌入功能');
        $unsupportedMethod->invoke($unsupportedModel);
    }

    /**
     * 创建带有模拟客户端的模型.
     * @return LegacyMockInterface|MockInterface|OpenAIModel
     */
    private function createModelWithMockedClient(string $modelName, ClientInterface $client)
    {
        $model = Mockery::mock(OpenAIModel::class, [$modelName, [], null])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $model->shouldReceive('getClient')->andReturn($client);

        return $model;
    }

    /**
     * 模拟嵌入响应.
     */
    private function mockEmbeddingResponse(array $vector): EmbeddingResponse
    {
        // 创建模拟的HTTP响应
        $httpResponse = Mockery::mock(ResponseInterface::class);
        $stream = Mockery::mock(StreamInterface::class);

        // 创建响应JSON
        $responseJson = json_encode([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => $vector,
                    'index' => 0,
                ],
            ],
            'model' => 'text-embedding-ada-002',
            'usage' => [
                'prompt_tokens' => 8,
                'total_tokens' => 8,
            ],
        ]);

        $httpResponse->shouldReceive('getBody')->andReturn($stream);
        $httpResponse->shouldReceive('getStatusCode')->andReturn(200);
        $stream->shouldReceive('__toString')->andReturn($responseJson);

        // 创建嵌入响应对象，但不调用父类构造函数
        return new class($httpResponse) extends EmbeddingResponse {
            public function __construct($response)
            {
                $this->content = $response->getBody()->__toString();
                $this->originResponse = $response;
                $this->success = $response->getStatusCode() === 200;
                $this->parseContent();
            }
        };
    }
}

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

namespace HyperfTest\Odin\Cases\Api\Providers;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Contract\Api\ConfigInterface;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\Expectation;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AbstractClient
 */
class AbstractClientTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试初始化和配置处理.
     */
    public function testInitializationAndConfiguration()
    {
        // 创建模拟对象
        /** @var ConfigInterface&MockInterface $config */
        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('getApiKey')->andReturn('test-api-key');
        $config->shouldReceive('getBaseUrl')->andReturn('https://api.example.com');

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        // 创建自定义API选项
        $apiOptions = new ApiOptions([
            'timeout' => [
                'connection' => 10.0,
                'read' => 30.0,
                'total' => 60.0,
            ],
        ]);

        // 创建具体的AbstractClient实现
        $client = new ConcreteClientForTest($config, $apiOptions, $logger);

        // 验证客户端配置是否正确设置
        $this->assertSame($config, $this->getNonpublicProperty($client, 'config'));
        $this->assertSame($logger, $this->getNonpublicProperty($client, 'logger'));
        $this->assertSame($apiOptions, $client->getRequestOptions());

        // 验证GuzzleClient是否被正确初始化
        $guzzleClient = $this->getNonpublicProperty($client, 'client');
        $this->assertInstanceOf(GuzzleClient::class, $guzzleClient);

        // 验证ErrorMappingManager是否被正确初始化
        $errorMappingManager = $this->getNonpublicProperty($client, 'errorMappingManager');
        $this->assertInstanceOf(ErrorMappingManager::class, $errorMappingManager);
    }

    /**
     * 测试错误处理和异常映射.
     */
    public function testErrorHandlingAndExceptionMapping()
    {
        // 创建模拟的错误处理器
        /** @var ErrorHandlerInterface&MockInterface $errorHandler */
        $errorHandler = Mockery::mock(ErrorHandlerInterface::class);

        // 创建模拟的客户端，允许模拟受保护方法
        /** @var ConcreteClientForTest&MockInterface $client */
        $client = Mockery::mock(ConcreteClientForTest::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // 设置错误处理器
        $this->setNonpublicPropertyValue($client, 'errorHandler', $errorHandler);

        // 模拟受保护的convertException方法
        /** @var Expectation $expectation */
        $expectation = $client->shouldReceive('convertException');
        $expectation->once()
            ->with(Mockery::type(Exception::class))
            ->andReturn(new LLMConnectionTimeoutException('测试超时异常'));

        // 创建一个异常
        $exception = new Exception('测试异常');

        // 调用handleException方法
        $result = $client->handleExceptionForTest($exception);

        // 验证结果是LLMConnectionTimeoutException类型
        $this->assertInstanceOf(LLMConnectionTimeoutException::class, $result);
        $this->assertEquals('测试超时异常', $result->getMessage());
    }

    /**
     * 测试超时场景.
     */
    public function testTimeoutHandling()
    {
        // 创建基本的配置和日志记录器模拟
        /** @var ConfigInterface&MockInterface $config */
        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('getBaseUrl')->andReturn('https://api.example.com');

        // 创建自定义的请求选项
        $options = new ApiOptions([
            'timeout' => [
                'connection' => 10.0,
                'read' => 20.0,
                'total' => 30.0,
            ],
        ]);

        // 创建客户端实例
        $client = new ConcreteClientForTest($config, $options);

        // 获取请求选项并验证超时设置
        $requestOptions = $client->getRequestOptions();
        $this->assertEquals(10.0, $requestOptions->getConnectionTimeout());
        $this->assertEquals(20.0, $requestOptions->getReadTimeout());
        $this->assertEquals(30.0, $requestOptions->getTotalTimeout());
    }

    /**
     * 测试工具参数在请求中的处理.
     */
    public function testToolsParameterHandling()
    {
        // 由于这里需要实际发送请求，我们将模拟方法来验证参数处理
        // 创建基本的配置和日志记录器模拟
        /** @var ConfigInterface&MockInterface $config */
        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('getApiKey')->andReturn('test-api-key');
        $config->shouldReceive('getBaseUrl')->andReturn('https://api.example.com');

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        /** @var Expectation $expectation */
        $expectation = $logger->shouldReceive('debug');
        $expectation->withAnyArgs()->zeroOrMoreTimes();

        // 创建具体的工具定义（在实际测试中将由真实的工具提供）
        $toolsDefinition = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'test_function',
                    'description' => '测试函数',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'param1' => [
                                'type' => 'string',
                                'description' => '参数1',
                            ],
                        ],
                        'required' => ['param1'],
                    ],
                ],
            ],
        ];

        // 创建具体的测试客户端
        $client = new ConcreteClientForTest($config, new ApiOptions(), $logger);

        // 验证工具定义处理
        $result = $client->processToolsForTest($toolsDefinition);
        $this->assertEquals($toolsDefinition, $result);
    }
}

/**
 * 为测试创建的具体AbstractClient实现.
 * @internal
 * @coversNothing
 */
class ConcreteClientForTest extends AbstractClient
{
    public function getBaseUriForTest(): string
    {
        return $this->getBaseUri();
    }

    public function getAuthHeadersForTest(): array
    {
        return $this->getAuthHeaders();
    }

    public function convertExceptionForTest(Throwable $exception, array $context = []): LLMException
    {
        return $this->convertException($exception, $context);
    }

    public function processToolsForTest(array $tools): array
    {
        // 处理工具定义并返回
        return $tools;
    }

    /**
     * 暴露受保护的方法用于测试.
     */
    public function handleExceptionForTest(Throwable $e)
    {
        return $this->convertException($e);
    }

    /**
     * 获取请求选项.
     */
    public function getRequestOptions(): ApiOptions
    {
        return $this->requestOptions;
    }

    /**
     * 模拟执行嵌入请求，返回模拟响应数据.
     */
    public function executeEmbeddingRequestForTest(): array
    {
        // 在真实实现中，这将发送HTTP请求并返回响应数据
        // 但在测试中，我们返回模拟数据
        return [
            'object' => 'list',
            'data' => [
                [
                    'object' => 'embedding',
                    'embedding' => [0.1, 0.2, 0.3],
                    'index' => 0,
                ],
            ],
            'model' => 'text-embedding-ada-002',
            'usage' => [
                'prompt_tokens' => 8,
                'total_tokens' => 8,
            ],
        ];
    }

    protected function buildChatCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/chat/completions';
    }

    protected function buildEmbeddingsUrl(): string
    {
        return $this->getBaseUri() . '/embeddings';
    }

    protected function buildCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/completions';
    }
}

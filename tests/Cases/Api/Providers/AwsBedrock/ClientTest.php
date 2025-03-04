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

namespace HyperfTest\Odin\Cases\Api\Providers\AwsBedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\Client;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\Client
 */
class ClientTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试Client初始化.
     */
    public function testClientInitialization()
    {
        // 创建配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 创建日志记录器
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        // 创建客户端
        $client = new Client($config, null, $logger);

        // 验证客户端配置是否正确设置
        $reflection = new ReflectionClass($client);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $this->assertSame($config, $configProperty->getValue($client));

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $this->assertSame($logger, $loggerProperty->getValue($client));

        // 验证ErrorMappingManager是否被正确初始化
        $errorMappingManager = $reflection->getProperty('errorMappingManager');
        $errorMappingManager->setAccessible(true);
        $this->assertInstanceOf(ErrorMappingManager::class, $errorMappingManager->getValue($client));
    }

    /**
     * 测试BedrockClient的初始化.
     */
    public function testBedrockClientInitialization()
    {
        // 创建配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 创建客户端
        $client = new Client($config);

        // 调用initClient方法初始化BedrockClient
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('initClient');
        $method->setAccessible(true);
        $method->invoke($client);

        // 验证BedrockClient是否被正确初始化
        $bedrockClient = $reflection->getProperty('bedrockClient');
        $bedrockClient->setAccessible(true);
        $this->assertInstanceOf(BedrockRuntimeClient::class, $bedrockClient->getValue($client));
    }

    /**
     * 测试buildChatCompletionsUrl方法.
     */
    public function testBuildChatCompletionsUrl()
    {
        // 创建配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildChatCompletionsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildChatCompletionsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 对于AWS Bedrock，这个方法应该返回空字符串，因为它不使用URL
        $this->assertEquals('', $url);
    }

    /**
     * 测试buildEmbeddingsUrl方法.
     */
    public function testBuildEmbeddingsUrl()
    {
        // 创建配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildEmbeddingsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildEmbeddingsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 对于AWS Bedrock，这个方法应该返回空字符串，因为它不使用URL
        $this->assertEquals('', $url);
    }

    /**
     * 测试getAuthHeaders方法.
     */
    public function testGetAuthHeaders()
    {
        // 创建配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getAuthHeaders方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($client);

        // 对于AWS Bedrock，这个方法应该返回空数组，因为它使用AWS SDK进行认证
        $this->assertEquals([], $headers);
    }
}

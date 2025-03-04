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

namespace HyperfTest\Odin\Cases\Api\Providers\AzureOpenAI;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\Providers\AzureOpenAI\Client;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AzureOpenAI\Client
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
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建日志记录器
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        // 创建客户端
        $client = new Client($config, null, $logger);

        // 验证客户端配置是否正确设置
        $this->assertSame($config, $this->getNonpublicProperty($client, 'config'));
        $this->assertSame($logger, $this->getNonpublicProperty($client, 'logger'));

        // 验证GuzzleClient是否被正确初始化
        $guzzleClient = $this->getNonpublicProperty($client, 'client');
        $this->assertInstanceOf(GuzzleClient::class, $guzzleClient);

        // 验证ErrorMappingManager是否被正确初始化
        $errorMappingManager = $this->getNonpublicProperty($client, 'errorMappingManager');
        $this->assertInstanceOf(ErrorMappingManager::class, $errorMappingManager);

        // 验证azureConfig是否被正确设置
        $azureConfig = $this->getNonpublicProperty($client, 'azureConfig');
        $this->assertSame($config, $azureConfig);
    }

    /**
     * 测试Azure特定的URL构建.
     */
    public function testBuildChatCompletionsUrl()
    {
        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildChatCompletionsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildChatCompletionsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 验证URL构建正确
        $expectedUrl = 'https://api.example.azure.com/openai/deployments/test-deployment/chat/completions?api-version=2023-05-15';
        $this->assertEquals($expectedUrl, $url);
    }

    /**
     * 测试部署路径构建.
     */
    public function testBuildDeploymentPath()
    {
        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildDeploymentPath方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildDeploymentPath');
        $method->setAccessible(true);
        $deploymentPath = $method->invoke($client);

        // 验证部署路径构建正确
        $expectedPath = 'https://api.example.azure.com/openai/deployments/test-deployment';
        $this->assertEquals($expectedPath, $deploymentPath);
    }

    /**
     * 测试Azure认证头设置.
     */
    public function testGetAuthHeaders()
    {
        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getAuthHeaders方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($client);

        // 验证请求头包含正确的API密钥
        $this->assertArrayHasKey('api-key', $headers);
        $this->assertEquals('test-api-key', $headers['api-key']);
    }

    /**
     * 测试getBaseUri方法.
     */
    public function testGetBaseUri()
    {
        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com/',  // 注意结尾的斜杠
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getBaseUri方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getBaseUri');
        $method->setAccessible(true);
        $baseUri = $method->invoke($client);

        // 验证baseUri正确（应该去掉结尾的斜杠）
        $this->assertEquals('https://api.example.azure.com', $baseUri);
    }

    /**
     * 测试嵌入 URL 构建方法.
     */
    public function testBuildEmbeddingsUrl()
    {
        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com',
            deploymentName: 'test-deployment',
            apiVersion: '2023-05-15'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildEmbeddingsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildEmbeddingsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 验证URL构建正确
        $this->assertEquals('https://api.example.com/openai/deployments/test-deployment/embeddings?api-version=2023-05-15', $url);
    }
}

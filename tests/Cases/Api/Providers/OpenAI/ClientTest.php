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

namespace HyperfTest\Odin\Cases\Api\Providers\OpenAI;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Odin\Api\Providers\OpenAI\Client;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\OpenAI\Client
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
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: 'test-org',
            baseUrl: 'https://api.example.com'
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
    }

    /**
     * 测试URL构建方法.
     */
    public function testBuildChatCompletionsUrl()
    {
        // 创建配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildChatCompletionsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildChatCompletionsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 验证URL构建正确
        $this->assertEquals('https://api.example.com/chat/completions', $url);
    }

    /**
     * 测试请求头设置（API密钥，组织等）.
     */
    public function testGetAuthHeaders()
    {
        // 创建配置，包含API密钥和组织
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: 'test-org',
            baseUrl: 'https://api.example.com'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getAuthHeaders方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($client);

        // 验证请求头包含API密钥和组织
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-api-key', $headers['Authorization']);
        $this->assertArrayHasKey('OpenAI-Organization', $headers);
        $this->assertEquals('test-org', $headers['OpenAI-Organization']);
    }

    /**
     * 测试请求头设置 - 仅API密钥，无组织.
     */
    public function testGetAuthHeadersWithoutOrganization()
    {
        // 创建配置，仅包含API密钥，无组织
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: '',
            baseUrl: 'https://api.example.com'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getAuthHeaders方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getAuthHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($client);

        // 验证请求头包含API密钥但不包含组织
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-api-key', $headers['Authorization']);
        $this->assertArrayNotHasKey('OpenAI-Organization', $headers);
    }

    /**
     * 测试getBaseUri方法.
     */
    public function testGetBaseUri()
    {
        // 创建配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com/'  // 注意结尾的斜杠
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的getBaseUri方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('getBaseUri');
        $method->setAccessible(true);
        $baseUri = $method->invoke($client);

        // 验证baseUri正确（应该去掉结尾的斜杠）
        $this->assertEquals('https://api.example.com', $baseUri);
    }

    /**
     * 测试嵌入 URL 构建方法.
     */
    public function testBuildEmbeddingsUrl()
    {
        // 创建配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com'
        );

        // 创建客户端
        $client = new Client($config);

        // 使用反射调用受保护的buildEmbeddingsUrl方法
        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('buildEmbeddingsUrl');
        $method->setAccessible(true);
        $url = $method->invoke($client);

        // 验证URL构建正确
        $this->assertEquals('https://api.example.com/embeddings', $url);
    }
}

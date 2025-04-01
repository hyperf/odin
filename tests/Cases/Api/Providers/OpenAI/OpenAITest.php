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

use Hyperf\Odin\Api\Providers\OpenAI\Client;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAI;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\OpenAI\OpenAI
 */
class OpenAITest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试OpenAI类的基本功能.
     */
    public function testGetClient()
    {
        // 创建OpenAI实例
        $openAI = new OpenAI();

        // 创建有效的配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: 'test-org',
            baseUrl: 'https://api.example.com'
        );

        // 获取客户端
        $client = $openAI->getClient($config);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);

        // 再次调用getClient，应该返回相同的实例（缓存）
        $client2 = $openAI->getClient($config);
        $this->assertSame($client, $client2);
    }

    /**
     * 测试缺少ApiKey时的异常.
     */
    public function testMissingApiKey()
    {
        $openAI = new OpenAI();

        // 创建缺少API Key的配置
        $config = new OpenAIConfig(
            apiKey: '',
            baseUrl: 'https://api.example.com'
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidApiKeyException::class);
        $this->expectExceptionMessage('API密钥不能为空');

        $openAI->getClient($config);
    }

    /**
     * 测试skipApiKeyValidation选项.
     */
    public function testSkipApiKeyValidation()
    {
        $openAI = new OpenAI();

        // 创建配置，虽然没有API密钥，但跳过验证
        $config = new OpenAIConfig(
            apiKey: '',
            baseUrl: 'https://api.example.com',
            skipApiKeyValidation: true
        );

        // 应该能够获取客户端而不抛出异常
        $client = $openAI->getClient($config);
        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * 测试缺少BaseUrl时的异常.
     */
    public function testMissingBaseUrl()
    {
        $openAI = new OpenAI();

        // 创建缺少BaseUrl的配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: ''
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidEndpointException::class);
        $this->expectExceptionMessage('基础URL不能为空');

        $openAI->getClient($config);
    }

    /**
     * 测试使用不同的参数获取不同的客户端实例.
     */
    public function testGetClientWithDifferentParams()
    {
        $openAI = new OpenAI();

        // 创建配置1
        $config1 = new OpenAIConfig(
            apiKey: 'test-api-key-1',
            baseUrl: 'https://api.example.com'
        );

        // 创建配置2
        $config2 = new OpenAIConfig(
            apiKey: 'test-api-key-2',
            baseUrl: 'https://api.example.com'
        );

        // 获取客户端1
        $client1 = $openAI->getClient($config1);

        // 获取客户端2
        $client2 = $openAI->getClient($config2);

        // 应该是不同的实例
        $this->assertNotSame($client1, $client2);
    }

    /**
     * 测试通过完整参数获取客户端.
     */
    public function testGetClientWithAllParams()
    {
        $openAI = new OpenAI();

        // 创建配置
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: 'test-org',
            baseUrl: 'https://api.example.com'
        );

        // 创建请求选项
        $options = new ApiOptions([
            'timeout' => [
                'connection' => 10.0,
                'read' => 30.0,
                'total' => 60.0,
            ],
        ]);

        // 由于Logger对象在序列化时会有问题，这里我们不传递Logger
        // 获取客户端
        $client = $openAI->getClient($config, $options);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);
    }
}

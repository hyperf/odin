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

use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\Providers\AzureOpenAI\Client;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAI
 */
class AzureOpenAITest extends AbstractTestCase
{
    /**
     * 测试基本的客户端获取.
     */
    public function testGetClient()
    {
        // 创建AzureOpenAI实例
        $azureOpenAI = new AzureOpenAI();

        // 创建有效的配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 获取客户端
        $client = $azureOpenAI->getClient($config);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);

        // 再次调用getClient，应该返回相同的实例（缓存）
        $client2 = $azureOpenAI->getClient($config);
        $this->assertSame($client, $client2);
    }

    /**
     * 测试使用不同的参数获取不同的客户端实例.
     */
    public function testGetClientWithDifferentParams()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建配置1
        $config1 = new AzureOpenAIConfig(
            apiKey: 'test-api-key-1',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment-1'
        );

        // 创建配置2
        $config2 = new AzureOpenAIConfig(
            apiKey: 'test-api-key-2',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment-2'
        );

        // 获取客户端1
        $client1 = $azureOpenAI->getClient($config1);

        // 获取客户端2
        $client2 = $azureOpenAI->getClient($config2);

        // 应该是不同的实例
        $this->assertNotSame($client1, $client2);
    }

    /**
     * 测试缺少API密钥时的异常.
     */
    public function testMissingApiKey()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建缺少API Key的配置
        $config = new AzureOpenAIConfig(
            apiKey: '',
            baseUrl: 'https://api.example.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidApiKeyException::class);
        $this->expectExceptionMessage('API密钥不能为空');

        $azureOpenAI->getClient($config);
    }

    /**
     * 测试缺少BaseUrl时的异常.
     */
    public function testMissingBaseUrl()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建缺少BaseUrl的配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: '',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidEndpointException::class);
        $this->expectExceptionMessage('基础URL不能为空');

        $azureOpenAI->getClient($config);
    }

    /**
     * 测试缺少ApiVersion时的异常.
     */
    public function testMissingApiVersion()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建缺少ApiVersion的配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com',
            apiVersion: '',
            deploymentName: 'test-deployment'
        );

        // 预期会抛出异常
        $this->expectException(LLMConfigurationException::class);
        $this->expectExceptionMessage('API版本不能为空');

        $azureOpenAI->getClient($config);
    }

    /**
     * 测试缺少DeploymentName时的异常.
     */
    public function testMissingDeploymentName()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建缺少DeploymentName的配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.com',
            apiVersion: '2023-05-15',
            deploymentName: ''
        );

        // 预期会抛出异常
        $this->expectException(LLMConfigurationException::class);
        $this->expectExceptionMessage('部署名称不能为空');

        $azureOpenAI->getClient($config);
    }

    /**
     * 测试通过完整参数获取客户端.
     */
    public function testGetClientWithAllParams()
    {
        $azureOpenAI = new AzureOpenAI();

        // 创建配置
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        // 创建请求选项
        $options = new ApiOptions([
            'timeout' => [
                'connection' => 10.0,
                'read' => 30.0,
                'total' => 60.0,
            ],
        ]);

        // 获取客户端
        $client = $azureOpenAI->getClient($config, $options);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);
    }
}

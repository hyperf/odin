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

use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;
use Hyperf\Odin\Model\AzureOpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(AzureOpenAIModel::class)]
class AzureOpenAIModelTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试 getClient 方法.
     */
    public function testGetClient()
    {
        // 使用 Mockery 替换 ClientFactory::createAzureOpenAIClient 方法
        $clientMock = Mockery::mock(ClientInterface::class);

        $clientFactoryMock = Mockery::mock('alias:' . ClientFactory::class);
        $clientFactoryMock->shouldReceive('createAzureOpenAIClient')
            ->once()
            ->withArgs(function ($config, $apiOptions, $logger) {
                // 验证 config 中的必要参数
                return isset($config['api_key']) && isset($config['base_url'], $config['deployment_name'], $config['api_version']);
            })
            ->andReturn($clientMock);

        $model = new AzureOpenAIModel('gpt-3.5-turbo', [
            'api_key' => 'test-key',
            'base_url' => 'https://myazure.openai.azure.com',
            'deployment_name' => 'test-deployment',
            'api_version' => '2023-05-15',
        ]);

        $client = $this->callNonpublicMethod($model, 'getClient');

        $this->assertSame($clientMock, $client);
    }

    /**
     * 测试 AzureOpenAIModel 不使用 processApiBaseUrl 方法.
     */
    public function testDoesNotProcessApiBaseUrl()
    {
        // Azure 模型不应该处理 API URL 路径，因为它有独特的 URL 结构
        $model = new AzureOpenAIModel('gpt-3.5-turbo', [
            'api_key' => 'test-key',
            'base_url' => 'https://myazure.openai.azure.com',
            'deployment_name' => 'test-deployment',
            'api_version' => '2023-05-15',
        ]);

        // 获取 getApiVersionPath 方法的返回值
        $reflection = new ReflectionMethod($model, 'getApiVersionPath');
        $reflection->setAccessible(true);
        $versionPath = $reflection->invoke($model);

        // 断言 Azure 模型不返回 API 版本路径
        $this->assertEquals('', $versionPath, 'Azure 模型不应该返回 API 版本路径');
    }
}

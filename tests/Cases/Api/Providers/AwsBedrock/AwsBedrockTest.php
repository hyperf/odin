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

use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrock;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsType;
use Hyperf\Odin\Api\Providers\AwsBedrock\Client;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrock
 */
class AwsBedrockTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试AwsBedrock类的基本功能.
     */
    public function testGetClient()
    {
        // 创建AwsBedrock实例
        $awsBedrock = new AwsBedrock();

        // 创建有效的配置，使用 invoke 类型以返回 Client 实例
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1',
            type: AwsType::INVOKE
        );

        // 获取客户端
        $client = $awsBedrock->getClient($config);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);

        // 再次调用getClient，应该返回相同的实例（缓存）
        $client2 = $awsBedrock->getClient($config);
        $this->assertSame($client, $client2);
    }

    /**
     * 测试缺少AccessKey和SecretKey时的异常.
     */
    public function testMissingCredentials()
    {
        $awsBedrock = new AwsBedrock();

        // 创建缺少AccessKey的配置
        $config = new AwsBedrockConfig(
            accessKey: '',
            secretKey: 'test-secret-key',
            region: 'us-east-1'
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidApiKeyException::class);
        $this->expectExceptionMessage('AWS访问密钥和密钥不能为空');

        $awsBedrock->getClient($config);

        // 测试缺少SecretKey的情况
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: '',
            region: 'us-east-1'
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidApiKeyException::class);
        $this->expectExceptionMessage('AWS访问密钥和密钥不能为空');

        $awsBedrock->getClient($config);
    }

    /**
     * 测试缺少Region时的异常.
     */
    public function testMissingRegion()
    {
        $awsBedrock = new AwsBedrock();

        // 创建缺少Region的配置
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: ''
        );

        // 预期会抛出异常
        $this->expectException(LLMInvalidEndpointException::class);
        $this->expectExceptionMessage('AWS区域不能为空');

        $awsBedrock->getClient($config);
    }

    /**
     * 测试使用不同的参数获取不同的客户端实例.
     */
    public function testGetClientWithDifferentParams()
    {
        $awsBedrock = new AwsBedrock();

        // 创建配置1
        $config1 = new AwsBedrockConfig(
            accessKey: 'test-access-key-1',
            secretKey: 'test-secret-key-1',
            region: 'us-east-1'
        );

        // 创建配置2
        $config2 = new AwsBedrockConfig(
            accessKey: 'test-access-key-2',
            secretKey: 'test-secret-key-2',
            region: 'us-east-1'
        );

        // 获取客户端1
        $client1 = $awsBedrock->getClient($config1);

        // 获取客户端2
        $client2 = $awsBedrock->getClient($config2);

        // 应该是不同的实例
        $this->assertNotSame($client1, $client2);
    }

    /**
     * 测试通过完整参数获取客户端.
     */
    public function testGetClientWithAllParams()
    {
        $awsBedrock = new AwsBedrock();

        // 创建配置，使用 invoke 类型以返回 Client 实例
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'us-east-1',
            type: AwsType::INVOKE
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
        $client = $awsBedrock->getClient($config, $options);

        // 验证返回的是Client实例
        $this->assertInstanceOf(Client::class, $client);
    }
}

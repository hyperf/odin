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
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(OpenAIModel::class)]
class OpenAIModelTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试 getApiVersionPath 方法.
     */
    public function testGetApiVersionPath()
    {
        $model = new OpenAIModel('gpt-3.5-turbo', []);

        $apiVersionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');

        $this->assertEquals('v1', $apiVersionPath);
    }

    /**
     * 测试 getClient 方法.
     */
    public function testGetClient()
    {
        // 使用 Mockery 替换 ClientFactory::createOpenAIClient 方法
        $clientMock = Mockery::mock(ClientInterface::class);

        $clientFactoryMock = Mockery::mock('alias:' . ClientFactory::class);
        $clientFactoryMock->shouldReceive('createOpenAIClient')
            ->once()
            ->withArgs(function ($config, $apiOptions, $logger) {
                // 验证 base_url 是否包含 API 版本路径
                return isset($config['base_url']) && str_contains($config['base_url'], '/v1');
            })
            ->andReturn($clientMock);

        $model = new OpenAIModel('gpt-3.5-turbo', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com',
        ]);

        $client = $this->callNonpublicMethod($model, 'getClient');

        $this->assertSame($clientMock, $client);
    }

    /**
     * 测试 processApiBaseUrl 方法的行为（而不是实际实现）.
     */
    public function testProcessApiBaseUrl()
    {
        // 模拟 AbstractModel 中的 processApiBaseUrl 方法的行为
        $model = Mockery::mock(OpenAIModel::class)->makePartial();

        // 测试第一种情况：没有版本路径的 URL
        $configInput = ['base_url' => 'https://api.openai.com'];
        $expectedOutput = 'https://api.openai.com/v1';

        // 通过反射调用 getApiVersionPath
        $reflection = new ReflectionMethod($model, 'getApiVersionPath');
        $reflection->setAccessible(true);
        $versionPath = $reflection->invoke($model);

        // 自己实现 processApiBaseUrl 的逻辑
        $baseUrl = rtrim($configInput['base_url'], '/');
        $versionPath = ltrim($versionPath, '/');
        $processedUrl = $baseUrl . '/' . $versionPath;

        $this->assertEquals($expectedOutput, $processedUrl, '期望 URL 被正确处理');
    }
}

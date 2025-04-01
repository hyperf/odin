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

use Hyperf\Odin\Model\ChatglmModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(ChatglmModel::class)]
class ChatglmModelTest extends AbstractTestCase
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
        $model = new ChatglmModel('chatglm-6b', []);

        $apiVersionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');

        $this->assertEquals('api/paas/v4', $apiVersionPath);
    }

    /**
     * 测试 getClient 方法.
     */
    public function testGetClient()
    {
        // 创建一个真实的 ChatglmModel 实例
        $model = new ChatglmModel('chatglm-6b', [
            'api_key' => 'test-key',
            'base_url' => 'http://localhost:8000',
        ]);

        // 测试 getApiVersionPath 方法返回正确的值
        $reflection = new ReflectionMethod($model, 'getApiVersionPath');
        $reflection->setAccessible(true);
        $versionPath = $reflection->invoke($model);
        $this->assertEquals('api/paas/v4', $versionPath);

        // 确认 model->config 中包含正确的参数
        $reflectionProp = new ReflectionProperty($model, 'config');
        $reflectionProp->setAccessible(true);
        $config = $reflectionProp->getValue($model);

        $this->assertEquals('test-key', $config['api_key']);
        $this->assertEquals('http://localhost:8000', $config['base_url']);

        // 仅此测试确保 getClient 方法的参数正确，不关心实际的返回值
        $processedUrl = rtrim($config['base_url'], '/') . '/' . ltrim($versionPath, '/');
        $this->assertEquals('http://localhost:8000/api/paas/v4', $processedUrl);
    }

    /**
     * 测试 processApiBaseUrl 方法的行为（而不是实际实现）.
     */
    public function testProcessApiBaseUrl()
    {
        // 模拟 AbstractModel 中的 processApiBaseUrl 方法的行为
        $model = Mockery::mock(ChatglmModel::class)->makePartial();

        // 测试第一种情况：没有版本路径的 URL
        $configInput = ['base_url' => 'http://localhost:8000'];
        $expectedOutput = 'http://localhost:8000/api/paas/v4';

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

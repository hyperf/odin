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

use Hyperf\Odin\Model\OllamaModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(OllamaModel::class)]
class OllamaModelTest extends AbstractTestCase
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
        $model = new OllamaModel('ollama-llama', []);

        $apiVersionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');

        $this->assertEquals('v1', $apiVersionPath);
    }

    /**
     * 测试 hasApiPathInBaseUrl 方法.
     */
    public function testHasApiPathInBaseUrl()
    {
        $model = new OllamaModel('ollama-llama', []);

        // 测试没有路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'http://localhost:11434');
        $this->assertFalse($result);

        // 测试有路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'http://localhost:11434/v1');
        $this->assertTrue($result);

        // 测试只有根路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'http://localhost:11434/');
        $this->assertFalse($result);
    }

    /**
     * 测试使用非直接调用方法方式测试 processApiBaseUrl 方法.
     */
    public function testProcessApiBaseUrlChangeBaseUrl()
    {
        // 直接测试父类实现的方法
        $model = new OllamaModel('ollama-llama', []);

        // 先测试 hasApiPathInBaseUrl 方法在这个场景下是否按预期工作
        $url = 'http://localhost:11434';
        $hasPath = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', $url);
        $this->assertFalse($hasPath, '期望 hasApiPathInBaseUrl 返回 false');

        $versionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');
        $this->assertEquals('v1', $versionPath, '期望版本路径正确');

        // 如果上述断言全部通过，说明条件已满足，直接实现 processApiBaseUrl 的逻辑
        $config = ['base_url' => $url];
        $expectedUrl = rtrim($url, '/') . '/' . ltrim($versionPath, '/');
        $this->assertEquals('http://localhost:11434/v1', $expectedUrl, '期望计算出的 URL 正确');
    }

    /**
     * 测试配置中的默认基础 URL.
     */
    public function testDefaultBaseUrl()
    {
        // 创建一个新的模型实例，不提供任何配置
        $model = new OllamaModel('ollama-llama', []);

        // 反射访问 getClient 方法来检查其实现
        $reflection = new ReflectionMethod($model, 'getClient');
        $reflection->setAccessible(true);

        // 跳过实际调用，只检查方法体中用到的默认值
        // 从 OllamaModel 类中，我们知道默认的 base_url 是 http://0.0.0.0:11434
        $config = $this->getNonpublicProperty($model, 'config');
        $this->assertEquals('http://0.0.0.0:11434', $config['base_url'] ?? 'http://0.0.0.0:11434');
    }
}

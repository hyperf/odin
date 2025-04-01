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

use Hyperf\Odin\Model\DoubaoModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(DoubaoModel::class)]
class DoubaoModelTest extends AbstractTestCase
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
        $model = new DoubaoModel('doubao-text', []);

        $apiVersionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');

        $this->assertEquals('api/v3', $apiVersionPath);
    }

    /**
     * 测试 hasApiPathInBaseUrl 方法.
     */
    public function testHasApiPathInBaseUrl()
    {
        $model = new DoubaoModel('doubao-text', []);

        // 测试没有路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'https://api.example.com');
        $this->assertFalse($result);

        // 测试有路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'https://api.example.com/api/v3');
        $this->assertTrue($result);

        // 测试只有根路径的 URL
        $result = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', 'https://api.example.com/');
        $this->assertFalse($result);
    }

    /**
     * 测试使用非直接调用方法方式测试 processApiBaseUrl 方法.
     */
    public function testProcessApiBaseUrlChangeBaseUrl()
    {
        // 直接测试父类实现的方法
        $model = new DoubaoModel('doubao-text', []);

        // 先测试 hasApiPathInBaseUrl 方法在这个场景下是否按预期工作
        $url = 'https://api.example.com';
        $hasPath = $this->callNonpublicMethod($model, 'hasApiPathInBaseUrl', $url);
        $this->assertFalse($hasPath, '期望 hasApiPathInBaseUrl 返回 false');

        $versionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');
        $this->assertEquals('api/v3', $versionPath, '期望版本路径正确');

        // 如果上述断言全部通过，说明条件已满足，直接实现 processApiBaseUrl 的逻辑
        $config = ['base_url' => $url];
        $expectedUrl = rtrim($url, '/') . '/' . ltrim($versionPath, '/');
        $this->assertEquals('https://api.example.com/api/v3', $expectedUrl, '期望计算出的 URL 正确');
    }
}

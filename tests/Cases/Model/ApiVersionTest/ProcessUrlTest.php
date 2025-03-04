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

namespace HyperfTest\Odin\Model\ApiVersionTest;

use Hyperf\Odin\Model\DoubaoModel;
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class ProcessUrlTest extends AbstractTestCase
{
    public function testOpenAIUrlProcessing()
    {
        // 创建一个可测试的OpenAI模型子类
        $model = new class('gpt-3.5-turbo', []) extends OpenAIModel {
            public function exposedProcessApiBaseUrl(array &$config): void
            {
                $this->processApiBaseUrl($config);
            }

            public function exposedHasApiPathInBaseUrl(string $url): bool
            {
                return $this->hasApiPathInBaseUrl($url);
            }
        };

        // 1. 测试没有路径的URL
        $baseUrl = 'https://api.example.com';
        $this->assertFalse($model->exposedHasApiPathInBaseUrl($baseUrl), "URL应该被识别为没有路径: {$baseUrl}");

        // 2. 测试处理没有路径的URL
        $config = ['base_url' => $baseUrl];
        $model->exposedProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/v1', $config['base_url'], "URL应该添加版本路径: {$baseUrl}");

        // 3. 测试已有路径的URL
        $baseUrl = 'https://api.example.com/v1';
        $this->assertTrue($model->exposedHasApiPathInBaseUrl($baseUrl), "URL应该被识别为有路径: {$baseUrl}");

        // 4. 测试处理已有路径的URL
        $config = ['base_url' => $baseUrl];
        $model->exposedProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/v1', $config['base_url'], "URL不应该改变: {$baseUrl}");
    }

    public function testDoubaoUrlProcessing()
    {
        // 创建一个可测试的Doubao模型子类
        $model = new class('doubao-text', []) extends DoubaoModel {
            public function exposedProcessApiBaseUrl(array &$config): void
            {
                $this->processApiBaseUrl($config);
            }

            public function exposedHasApiPathInBaseUrl(string $url): bool
            {
                return $this->hasApiPathInBaseUrl($url);
            }
        };

        // 1. 测试没有路径的URL
        $baseUrl = 'https://api.example.com';
        $this->assertFalse($model->exposedHasApiPathInBaseUrl($baseUrl), "URL应该被识别为没有路径: {$baseUrl}");

        // 2. 测试处理没有路径的URL
        $config = ['base_url' => $baseUrl];
        $model->exposedProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/api/v3', $config['base_url'], "URL应该添加版本路径: {$baseUrl}");
    }
}

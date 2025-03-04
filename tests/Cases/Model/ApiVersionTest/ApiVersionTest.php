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

use Hyperf\Odin\Model\AbstractModel;
use Hyperf\Odin\Model\ChatglmModel;
use Hyperf\Odin\Model\DoubaoModel;
use Hyperf\Odin\Model\OllamaModel;
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(AbstractModel::class)]
class ApiVersionTest extends AbstractTestCase
{
    /**
     * 测试OpenAI模型的API版本路径.
     */
    public function testOpenAIModelApiVersionPath()
    {
        $model = new OpenAIModel('gpt-3.5-turbo', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.example.com',
        ]);

        $this->assertEquals('v1', $this->callNonpublicMethod($model, 'getApiVersionPath'));
    }

    /**
     * 测试豆包模型的API版本路径.
     */
    public function testDoubaoModelApiVersionPath()
    {
        $model = new DoubaoModel('doubao-text', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.example.com',
        ]);

        $this->assertEquals('api/v3', $this->callNonpublicMethod($model, 'getApiVersionPath'));
    }

    /**
     * 测试ChatGLM模型的API版本路径.
     */
    public function testChatglmModelApiVersionPath()
    {
        $model = new ChatglmModel('chatglm-text', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.example.com',
        ]);

        $this->assertEquals('api/paas/v4', $this->callNonpublicMethod($model, 'getApiVersionPath'));
    }

    /**
     * 测试OpenAI模型URL处理.
     */
    public function testOpenAIUrlProcessing()
    {
        // 创建可以访问protected方法的测试子类
        $model = new class('gpt-3.5-turbo', []) extends OpenAIModel {
            public function publicProcessApiBaseUrl(array &$config): void
            {
                $this->processApiBaseUrl($config);
            }
        };

        // 测试处理基本URL
        $config = ['base_url' => 'https://api.example.com'];
        $model->publicProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/v1', $config['base_url']);

        // 测试已包含路径的URL
        $config = ['base_url' => 'https://api.example.com/v1'];
        $model->publicProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/v1', $config['base_url']); // 不变

        // 测试末尾有斜杠的URL
        $config = ['base_url' => 'https://api.example.com/'];
        $model->publicProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/v1', $config['base_url']);
    }

    /**
     * 测试豆包模型URL处理.
     */
    public function testDoubaoUrlProcessing()
    {
        // 创建可以访问protected方法的测试子类
        $model = new class('doubao-text', []) extends DoubaoModel {
            public function publicProcessApiBaseUrl(array &$config): void
            {
                $this->processApiBaseUrl($config);
            }
        };

        // 测试处理基本URL
        $config = ['base_url' => 'https://api.example.com'];
        $model->publicProcessApiBaseUrl($config);
        $this->assertEquals('https://api.example.com/api/v3', $config['base_url']);
    }

    /**
     * 测试Ollama模型URL处理.
     */
    public function testOllamaUrlProcessing()
    {
        // 创建可以访问protected方法的测试子类
        $model = new class('ollama-text', []) extends OllamaModel {
            public function publicProcessApiBaseUrl(array &$config): void
            {
                $this->processApiBaseUrl($config);
            }
        };

        // 测试处理基本URL
        $config = ['base_url' => 'http://localhost:11434'];
        $model->publicProcessApiBaseUrl($config);
        $this->assertEquals('http://localhost:11434/v1', $config['base_url']);
    }
}

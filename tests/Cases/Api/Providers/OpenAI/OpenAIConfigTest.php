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

use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig
 */
class OpenAIConfigTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testBasicConstruction()
    {
        $config = new OpenAIConfig(
            apiKey: 'test-api-key',
            organization: 'test-org',
            baseUrl: 'https://api.example.com',
            skipApiKeyValidation: false
        );

        $this->assertEquals('test-api-key', $config->getApiKey());
        $this->assertEquals('test-org', $config->getOrganization());
        $this->assertEquals('https://api.example.com', $config->getBaseUrl());
        $this->assertFalse($config->shouldSkipApiKeyValidation());
    }

    /**
     * 测试默认值
     */
    public function testDefaultValues()
    {
        $config = new OpenAIConfig(
            apiKey: 'test-api-key'
        );

        $this->assertEquals('test-api-key', $config->getApiKey());
        $this->assertEquals('', $config->getOrganization());
        $this->assertEquals('https://api.openai.com', $config->getBaseUrl());
        $this->assertFalse($config->shouldSkipApiKeyValidation());
    }

    /**
     * 测试skipApiKeyValidation选项.
     */
    public function testSkipApiKeyValidation()
    {
        $config = new OpenAIConfig(
            apiKey: '',
            skipApiKeyValidation: true
        );

        $this->assertTrue($config->shouldSkipApiKeyValidation());
    }

    /**
     * 测试从数组创建配置.
     */
    public function testFromArray()
    {
        $array = [
            'api_key' => 'array-api-key',
            'organization' => 'array-org',
            'base_url' => 'https://array.example.com',
            'skip_api_key_validation' => true,
        ];

        $config = OpenAIConfig::fromArray($array);

        $this->assertEquals('array-api-key', $config->getApiKey());
        $this->assertEquals('array-org', $config->getOrganization());
        $this->assertEquals('https://array.example.com', $config->getBaseUrl());
        $this->assertTrue($config->shouldSkipApiKeyValidation());
    }

    /**
     * 测试从部分数组创建配置.
     */
    public function testFromPartialArray()
    {
        $array = [
            'api_key' => 'array-api-key',
        ];

        $config = OpenAIConfig::fromArray($array);

        $this->assertEquals('array-api-key', $config->getApiKey());
        $this->assertEquals('', $config->getOrganization());
        $this->assertEquals('https://api.openai.com', $config->getBaseUrl());
        $this->assertFalse($config->shouldSkipApiKeyValidation());
    }

    /**
     * 测试从空数组创建配置.
     */
    public function testFromEmptyArray()
    {
        $config = OpenAIConfig::fromArray([]);

        $this->assertEquals('', $config->getApiKey());
        $this->assertEquals('', $config->getOrganization());
        $this->assertEquals('https://api.openai.com', $config->getBaseUrl());
        $this->assertFalse($config->shouldSkipApiKeyValidation());
    }
}

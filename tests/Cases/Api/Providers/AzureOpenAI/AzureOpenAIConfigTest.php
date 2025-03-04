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

use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig
 */
class AzureOpenAIConfigTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testBasicConstruction()
    {
        $config = new AzureOpenAIConfig(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.example.azure.com',
            apiVersion: '2023-05-15',
            deploymentName: 'test-deployment'
        );

        $this->assertEquals('test-api-key', $config->getApiKey());
        $this->assertEquals('https://api.example.azure.com', $config->getBaseUrl());
        $this->assertEquals('2023-05-15', $config->getApiVersion());
        $this->assertEquals('test-deployment', $config->getDeploymentName());
    }

    /**
     * 测试从数组创建配置.
     */
    public function testFromArray()
    {
        $array = [
            'api_key' => 'array-api-key',
            'api_base' => 'https://array.azure.com',
            'api_version' => '2023-06-01',
            'deployment_name' => 'array-deployment',
        ];

        $config = AzureOpenAIConfig::fromArray($array);

        $this->assertEquals('array-api-key', $config->getApiKey());
        $this->assertEquals('https://array.azure.com', $config->getBaseUrl());
        $this->assertEquals('2023-06-01', $config->getApiVersion());
        $this->assertEquals('array-deployment', $config->getDeploymentName());
    }

    /**
     * 测试从部分数组创建配置.
     */
    public function testFromPartialArray()
    {
        $array = [
            'api_key' => 'array-api-key',
            'api_base' => 'https://array.azure.com',
            'deployment_name' => 'array-deployment',
        ];

        $config = AzureOpenAIConfig::fromArray($array);

        $this->assertEquals('array-api-key', $config->getApiKey());
        $this->assertEquals('https://array.azure.com', $config->getBaseUrl());
        $this->assertEquals('2023-05-15', $config->getApiVersion());  // 默认值
        $this->assertEquals('array-deployment', $config->getDeploymentName());
    }

    /**
     * 测试从空数组创建配置.
     */
    public function testFromEmptyArray()
    {
        $config = AzureOpenAIConfig::fromArray([]);

        $this->assertEquals('', $config->getApiKey());
        $this->assertEquals('', $config->getBaseUrl());
        $this->assertEquals('2023-05-15', $config->getApiVersion()); // 默认值
        $this->assertEquals('', $config->getDeploymentName());
    }
}

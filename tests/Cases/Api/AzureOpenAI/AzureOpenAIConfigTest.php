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

namespace HyperfTest\Odin\Cases\Api\AzureOpenAI;

use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAIConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class AzureOpenAIConfigTest extends AbstractTestCase
{
    public function testGetApiKey()
    {
        $config = new AzureOpenAIConfig(['api_key' => 'test_api_key']);
        $this->assertSame('test_api_key', $config->getApiKey());
    }

    public function testGetBaseUrl()
    {
        $config = new AzureOpenAIConfig(['api_base' => 'https://api.example.com']);
        $this->assertSame('https://api.example.com', $config->getBaseUrl());
    }

    public function testGetApiVersion()
    {
        $config = new AzureOpenAIConfig(['api_version' => 'v1']);
        $this->assertSame('v1', $config->getApiVersion());
    }

    public function testGetDeploymentName()
    {
        $config = new AzureOpenAIConfig(['deployment_name' => 'test_deployment']);
        $this->assertSame('test_deployment', $config->getDeploymentName());
    }

    public function testGetConfig()
    {
        $configArray = [
            'api_key' => 'test_api_key',
            'api_base' => 'https://api.example.com',
            'api_version' => 'v1',
            'deployment_name' => 'test_deployment',
        ];
        $config = new AzureOpenAIConfig($configArray);
        $this->assertSame($configArray, $config->getConfig());
    }
}

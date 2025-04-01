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

use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig
 */
class AwsBedrockConfigTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testBasicConstruction()
    {
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key',
            region: 'ap-northeast-1'
        );

        $this->assertEquals('test-access-key', $config->accessKey);
        $this->assertEquals('test-secret-key', $config->secretKey);
        $this->assertEquals('ap-northeast-1', $config->region);
    }

    /**
     * 测试默认值
     */
    public function testDefaultValues()
    {
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key'
        );

        $this->assertEquals('test-access-key', $config->accessKey);
        $this->assertEquals('test-secret-key', $config->secretKey);
        $this->assertEquals('us-east-1', $config->region);
    }

    /**
     * 测试 getApiKey 方法返回空字符串.
     */
    public function testGetApiKey()
    {
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key'
        );

        $this->assertEquals('', $config->getApiKey());
    }

    /**
     * 测试 getBaseUrl 方法返回空字符串.
     */
    public function testGetBaseUrl()
    {
        $config = new AwsBedrockConfig(
            accessKey: 'test-access-key',
            secretKey: 'test-secret-key'
        );

        $this->assertEquals('', $config->getBaseUrl());
    }
}

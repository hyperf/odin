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

namespace HyperfTest\Odin\Cases\Api\RequestOptions;

use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\RequestOptions\ApiOptions
 */
class ApiOptionsTest extends AbstractTestCase
{
    /**
     * 测试默认构造函数初始化值
     */
    public function testDefaultConstructor()
    {
        $options = new ApiOptions();

        // 验证默认超时设置
        $this->assertEquals(5.0, $options->getConnectionTimeout());
        $this->assertEquals(10.0, $options->getWriteTimeout());
        $this->assertEquals(300.0, $options->getReadTimeout());
        $this->assertEquals(350.0, $options->getTotalTimeout());
        $this->assertEquals(120.0, $options->getThinkingTimeout());
        $this->assertEquals(30.0, $options->getStreamChunkTimeout());
        $this->assertEquals(60.0, $options->getStreamFirstChunkTimeout());

        // 验证自定义错误映射规则默认为空数组
        $this->assertEquals([], $options->getCustomErrorMappingRules());
    }

    /**
     * 测试自定义参数构造.
     */
    public function testCustomConstructor()
    {
        $customOptions = [
            'timeout' => [
                'connection' => 15.0,
                'read' => 200.0,
                'total' => 250.0,
            ],
            'custom_error_mapping_rules' => [
                'rate_limit_exceeded' => 'RateLimitExceededException',
            ],
        ];

        $options = new ApiOptions($customOptions);

        // 验证自定义超时设置正确应用
        $this->assertEquals(15.0, $options->getConnectionTimeout());
        $this->assertEquals(10.0, $options->getWriteTimeout()); // 未修改的值应保持默认
        $this->assertEquals(200.0, $options->getReadTimeout());
        $this->assertEquals(250.0, $options->getTotalTimeout());

        // 验证自定义错误映射规则
        $this->assertEquals(
            ['rate_limit_exceeded' => 'RateLimitExceededException'],
            $options->getCustomErrorMappingRules()
        );
    }

    /**
     * 测试超时设置.
     */
    public function testTimeoutSettings()
    {
        $options = new ApiOptions();

        // 由于ApiOptions类没有提供setter方法，我们使用反射来修改属性
        $timeout = $this->getNonpublicProperty($options, 'timeout');
        $timeout['connection'] = 25.0;
        $this->setNonpublicPropertyValue($options, 'timeout', $timeout);

        // 验证修改后的值
        $this->assertEquals(25.0, $options->getConnectionTimeout());
    }

    /**
     * 测试fromArray静态方法.
     */
    public function testFromArrayMethod()
    {
        $customOptions = [
            'timeout' => [
                'connection' => 30.0,
                'read' => 150.0,
            ],
        ];

        $options = ApiOptions::fromArray($customOptions);

        // 验证通过静态方法创建的实例
        $this->assertInstanceOf(ApiOptions::class, $options);
        $this->assertEquals(30.0, $options->getConnectionTimeout());
        $this->assertEquals(150.0, $options->getReadTimeout());
    }

    /**
     * 测试toArray方法返回完整配置.
     */
    public function testToArrayMethod()
    {
        $customOptions = [
            'timeout' => [
                'connection' => 8.0,
                'write' => 20.0,
            ],
            'custom_error_mapping_rules' => [
                'invalid_request' => 'InvalidRequestException',
            ],
        ];

        $options = new ApiOptions($customOptions);
        $config = $options->toArray();

        // 验证返回的数组包含所有配置
        $this->assertIsArray($config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('custom_error_mapping_rules', $config);

        // 验证超时设置
        $this->assertEquals(8.0, $config['timeout']['connection']);
        $this->assertEquals(20.0, $config['timeout']['write']);

        // 验证自定义错误映射
        $this->assertEquals(
            ['invalid_request' => 'InvalidRequestException'],
            $config['custom_error_mapping_rules']
        );
    }
}

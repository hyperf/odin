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

namespace HyperfTest\Odin\Cases\Exception\LLMException;

use Hyperf\Odin\Exception\LLMException\ErrorCode;
use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\LLMConfigurationException
 */
class LLMConfigurationExceptionTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        $message = '测试配置异常';
        $code = 123;
        $previous = new RuntimeException('前置异常');
        $errorCode = ErrorCode::CONFIG_INVALID_API_KEY;

        $exception = new LLMConfigurationException($message, $code, $previous, $errorCode);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($errorCode, $exception->getErrorCode());
    }

    /**
     * 测试默认参数值.
     */
    public function testDefaultParameterValues()
    {
        $exception = new LLMConfigurationException('测试异常');

        $this->assertEquals('测试异常', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals(ErrorCode::CONFIG_ERROR_BASE, $exception->getErrorCode());
    }

    /**
     * 测试错误码自动计算.
     */
    public function testErrorCodeCalculation()
    {
        $code = 42;
        $exception = new LLMConfigurationException('测试异常', $code);

        // 验证错误码是否正确计算（基数+代码）
        $this->assertEquals(ErrorCode::CONFIG_ERROR_BASE + $code, $exception->getErrorCode());
    }

    /**
     * 测试明确指定错误码.
     */
    public function testExplicitErrorCode()
    {
        $errorCode = ErrorCode::CONFIG_INVALID_ENDPOINT;
        $exception = new LLMConfigurationException('无效的终端点', 0, null, $errorCode);

        $this->assertEquals($errorCode, $exception->getErrorCode());
    }

    /**
     * 测试配置异常的错误基码.
     */
    public function testConfigErrorBase()
    {
        $this->assertEquals(ErrorCode::CONFIG_ERROR_BASE, 1000);
    }
}

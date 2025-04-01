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
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\LLMNetworkException
 */
class LLMNetworkExceptionTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        $message = '测试网络异常';
        $code = 123;
        $previous = new RuntimeException('前置异常');
        $errorCode = ErrorCode::NETWORK_CONNECTION_ERROR;

        $exception = new LLMNetworkException($message, $code, $previous, $errorCode);

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
        $exception = new LLMNetworkException('测试异常');

        $this->assertEquals('测试异常', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals(ErrorCode::NETWORK_ERROR_BASE, $exception->getErrorCode());
    }

    /**
     * 测试错误码自动计算.
     */
    public function testErrorCodeCalculation()
    {
        $code = 42;
        $exception = new LLMNetworkException('测试异常', $code);

        // 验证错误码是否正确计算（基数+代码）
        $this->assertEquals(ErrorCode::NETWORK_ERROR_BASE + $code, $exception->getErrorCode());
    }

    /**
     * 测试明确指定错误码.
     */
    public function testExplicitErrorCode()
    {
        $errorCode = ErrorCode::NETWORK_READ_TIMEOUT;
        $exception = new LLMNetworkException('读取超时', 0, null, $errorCode);

        $this->assertEquals($errorCode, $exception->getErrorCode());
    }

    /**
     * 测试网络异常的错误基码.
     */
    public function testNetworkErrorBase()
    {
        $this->assertEquals(ErrorCode::NETWORK_ERROR_BASE, 2000);
    }
}

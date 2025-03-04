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
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\LLMApiException
 */
class LLMApiExceptionTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        $message = '测试API异常';
        $code = 123;
        $previous = new RuntimeException('前置异常');
        $errorCode = ErrorCode::API_RATE_LIMIT;
        $statusCode = 429;

        $exception = new LLMApiException($message, $code, $previous, $errorCode, $statusCode);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($errorCode, $exception->getErrorCode());
        $this->assertEquals($statusCode, $exception->getStatusCode());
    }

    /**
     * 测试默认参数值.
     */
    public function testDefaultParameterValues()
    {
        $exception = new LLMApiException('测试异常');

        $this->assertEquals('测试异常', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals(ErrorCode::API_ERROR_BASE, $exception->getErrorCode());
        $this->assertNull($exception->getStatusCode());
    }

    /**
     * 测试状态码 Getter 方法.
     */
    public function testGetStatusCode()
    {
        $statusCode = 400;
        $exception = new LLMApiException('无效请求', 0, null, 0, $statusCode);

        $this->assertEquals($statusCode, $exception->getStatusCode());
    }

    /**
     * 测试错误码自动计算.
     */
    public function testErrorCodeCalculation()
    {
        $code = 42;
        $exception = new LLMApiException('测试异常', $code);

        // 验证错误码是否正确计算（基数+代码）
        $this->assertEquals(ErrorCode::API_ERROR_BASE + $code, $exception->getErrorCode());
    }

    /**
     * 测试明确指定错误码.
     */
    public function testExplicitErrorCode()
    {
        $errorCode = ErrorCode::API_INVALID_REQUEST;
        $exception = new LLMApiException('无效请求', 0, null, $errorCode);

        $this->assertEquals($errorCode, $exception->getErrorCode());
    }
}

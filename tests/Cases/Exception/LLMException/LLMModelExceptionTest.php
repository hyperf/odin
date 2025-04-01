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
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\LLMModelException
 */
class LLMModelExceptionTest extends AbstractTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testConstructor()
    {
        $message = '测试模型异常';
        $code = 123;
        $previous = new RuntimeException('前置异常');
        $errorCode = ErrorCode::MODEL_CONTENT_FILTER;
        $model = 'gpt-4';

        $exception = new LLMModelException($message, $code, $previous, $errorCode, $model);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($errorCode, $exception->getErrorCode());
        $this->assertEquals($model, $exception->getModel());
    }

    /**
     * 测试默认参数值.
     */
    public function testDefaultParameterValues()
    {
        $exception = new LLMModelException('测试异常');

        $this->assertEquals('测试异常', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertEquals(ErrorCode::MODEL_ERROR_BASE, $exception->getErrorCode());
        $this->assertNull($exception->getModel());
    }

    /**
     * 测试模型名称 Getter 方法.
     */
    public function testGetModel()
    {
        $model = 'gpt-3.5-turbo';
        $exception = new LLMModelException('模型错误', 0, null, 0, $model);

        $this->assertEquals($model, $exception->getModel());
    }

    /**
     * 测试错误码自动计算.
     */
    public function testErrorCodeCalculation()
    {
        $code = 42;
        $exception = new LLMModelException('测试异常', $code);

        // 验证错误码是否正确计算（基数+代码）
        $this->assertEquals(ErrorCode::MODEL_ERROR_BASE + $code, $exception->getErrorCode());
    }

    /**
     * 测试明确指定错误码.
     */
    public function testExplicitErrorCode()
    {
        $errorCode = ErrorCode::MODEL_CONTEXT_LENGTH;
        $exception = new LLMModelException('上下文长度超出限制', 0, null, $errorCode);

        $this->assertEquals($errorCode, $exception->getErrorCode());
    }
}

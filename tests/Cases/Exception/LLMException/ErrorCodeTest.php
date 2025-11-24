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
use HyperfTest\Odin\Cases\AbstractTestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\ErrorCode
 */
class ErrorCodeTest extends AbstractTestCase
{
    /**
     * 测试错误码常量是否正确定义.
     */
    public function testErrorCodeConstants()
    {
        // 验证配置错误码
        $this->assertIsInt(ErrorCode::CONFIG_INVALID_API_KEY);
        $this->assertIsInt(ErrorCode::CONFIG_INVALID_ENDPOINT);

        // 验证网络错误码
        $this->assertIsInt(ErrorCode::NETWORK_CONNECTION_ERROR);
        $this->assertIsInt(ErrorCode::NETWORK_READ_TIMEOUT);

        // 验证API错误码
        $this->assertIsInt(ErrorCode::API_RATE_LIMIT);
        $this->assertIsInt(ErrorCode::API_INVALID_REQUEST);
        $this->assertIsInt(ErrorCode::API_SERVER_ERROR);

        // 验证模型错误码
        $this->assertIsInt(ErrorCode::MODEL_CONTENT_FILTER);
        $this->assertIsInt(ErrorCode::MODEL_CONTEXT_LENGTH);
    }

    /**
     * 测试获取错误码消息.
     */
    public function testGetMessage()
    {
        $message = ErrorCode::getMessage(ErrorCode::API_RATE_LIMIT);
        $this->assertIsString($message);
        $this->assertNotEmpty($message);

        // 测试未知错误码
        $unknownMessage = ErrorCode::getMessage(999999);
        $this->assertEquals('Unknown error', $unknownMessage);
    }

    /**
     * 测试获取错误码建议.
     */
    public function testGetSuggestion()
    {
        $suggestion = ErrorCode::getSuggestion(ErrorCode::NETWORK_CONNECTION_ERROR);
        $this->assertIsString($suggestion);
        $this->assertNotEmpty($suggestion);

        // 测试未知错误码
        $unknownSuggestion = ErrorCode::getSuggestion(999999);
        $this->assertIsString($unknownSuggestion);
    }

    /**
     * 测试错误消息映射表完整性.
     */
    public function testErrorMessagesCompleteness()
    {
        $reflectionClass = new ReflectionClass(ErrorCode::class);
        $constants = $reflectionClass->getConstants();
        $messages = ErrorCode::getErrorMessages();

        foreach ($constants as $name => $value) {
            // 只测试错误码常量（数值大于1000的整数常量）
            if (is_int($value) && $value >= 1000 && ! str_contains($name, '_BASE')) {
                $this->assertArrayHasKey($value, $messages, "错误码 {$name} 没有对应的错误消息");
            }
        }
    }

    /**
     * 测试错误码基础值是否正确.
     */
    public function testErrorCodeBases()
    {
        $this->assertEquals(1000, ErrorCode::CONFIG_ERROR_BASE);
        $this->assertEquals(2000, ErrorCode::NETWORK_ERROR_BASE);
        $this->assertEquals(3000, ErrorCode::API_ERROR_BASE);
        $this->assertEquals(4000, ErrorCode::MODEL_ERROR_BASE);
    }
}

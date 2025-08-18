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

namespace Hyperf\Odin\Exception\LLMException;

use Hyperf\Odin\Exception\LLMException;
use Throwable;

/**
 * API调用相关错误的基类.
 *
 * 这个类处理所有与API调用相关的错误，如请求格式错误、API请求受限等。
 * 错误码范围：3000-3999
 */
class LLMApiException extends LLMException
{
    /**
     * API错误的错误码基数.
     */
    private const ERROR_CODE_BASE = 3000;

    /**
     * 创建一个新的API异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, int $statusCode = 500)
    {
        // 如果没有提供错误码，则使用默认基数
        $errorCode = $errorCode ?: (self::ERROR_CODE_BASE + $code);
        parent::__construct($message, $code, $previous, $errorCode, $statusCode);
    }
}

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
 * 网络相关错误的基类.
 *
 * 这个类处理所有与网络相关的错误，如连接超时、读取超时等。
 * 错误码范围：2000-2999
 */
class LLMNetworkException extends LLMException
{
    /**
     * 网络错误的错误码基数.
     */
    private const ERROR_CODE_BASE = 2000;

    /**
     * 创建一个新的网络异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, int $statusCode = 500)
    {
        // 如果没有提供错误码，则使用默认基数
        $errorCode = $errorCode ?: (self::ERROR_CODE_BASE + $code);
        parent::__construct($message, $code, $previous, $errorCode, $statusCode);
    }
}

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

namespace Hyperf\Odin\Exception;

use Exception;
use Throwable;

/**
 * Odin异常基类，所有异常都应包含HTTP状态码和错误代码.
 */
class OdinException extends Exception
{
    /**
     * HTTP状态码.
     */
    protected int $statusCode;

    /**
     * 错误代码.
     */
    protected int $errorCode = 0;

    /**
     * 创建一个新的异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, int $statusCode = 500)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode ?: $code;
        $this->statusCode = $statusCode;
    }

    /**
     * 获取HTTP状态码.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 设置HTTP状态码.
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * 获取错误代码.
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}

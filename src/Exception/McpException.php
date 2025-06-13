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

use Throwable;

class McpException extends OdinException
{
    /**
     * 错误代码.
     */
    protected int $errorCode = 0;

    /**
     * 创建一个新的异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0)
    {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode ?: $code;
    }

    /**
     * 获取错误代码.
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}

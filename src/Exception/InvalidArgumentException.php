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

/**
 * 参数验证异常，自动设置400状态码.
 */
class InvalidArgumentException extends LLMException
{
    public function __construct(string $message = 'Invalid argument', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, $code ?: 400, 400);
    }
}

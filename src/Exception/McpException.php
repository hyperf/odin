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
     * 创建一个新的异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, int $statusCode = 500)
    {
        parent::__construct($message, $code, $previous, $errorCode, $statusCode);
    }
}

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
 * 所有LLM相关异常的基类.
 *
 * 这个类是所有LLM异常的基础类，提供了通用的方法和属性。
 * 具体的异常类型应该继承这个类，或者继承它的子类。
 */
class LLMException extends OdinException
{
    /**
     * 创建一个新的异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, int $statusCode = 500)
    {
        parent::__construct($message, $code, $previous, $errorCode, $statusCode);
    }
}

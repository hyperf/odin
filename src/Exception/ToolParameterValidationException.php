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
 * 工具参数验证异常.
 */
class ToolParameterValidationException extends Exception
{
    /**
     * 验证错误信息数组.
     */
    protected array $validationErrors = [];

    /**
     * 构造函数.
     *
     * @param string $message 错误消息
     * @param array $validationErrors 验证错误信息数组
     * @param int $code 错误代码
     * @param null|Throwable $previous 上一个异常
     */
    public function __construct(
        string $message = '',
        array $validationErrors = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->validationErrors = $validationErrors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * 获取验证错误信息.
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}

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
 * 模型相关错误的基类.
 *
 * 这个类处理所有与模型相关的错误，如内容过滤、上下文长度超出限制等。
 * 错误码范围：4000-4999
 */
class LLMModelException extends LLMException
{
    /**
     * 模型错误的错误码基数.
     */
    private const ERROR_CODE_BASE = 4000;

    /**
     * 模型名称.
     */
    protected ?string $model = null;

    /**
     * 创建一个新的模型异常实例.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, int $errorCode = 0, ?string $model = null, ?int $statusCode = null)
    {
        // 如果没有提供错误码，则使用默认基数
        $errorCode = $errorCode ?: (self::ERROR_CODE_BASE + $code);
        parent::__construct($message, $code, $previous, $errorCode, $statusCode);

        $this->model = $model;
    }

    /**
     * 获取模型名称.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }
}

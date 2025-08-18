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

namespace Hyperf\Odin\Exception\LLMException\Model;

use Hyperf\Odin\Exception\LLMException;
use Throwable;

/**
 * 模型不支持嵌入功能异常.
 */
class LLMEmbeddingNotSupportedException extends LLMException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 2103;

    /**
     * 构造函数.
     *
     * @param string $message 错误消息
     * @param null|Throwable $previous 上一个异常
     * @param string $model 模型名称
     */
    public function __construct(
        string $message = '模型不支持嵌入功能',
        ?Throwable $previous = null,
        protected string $model = ''
    ) {
        parent::__construct($message, self::ERROR_CODE, $previous, self::ERROR_CODE, 400);
    }

    /**
     * 获取模型名称.
     */
    public function getModel(): string
    {
        return $this->model;
    }
}

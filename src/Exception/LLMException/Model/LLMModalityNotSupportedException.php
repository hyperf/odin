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

use Hyperf\Odin\Exception\LLMException\ErrorMessage;
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use Throwable;

/**
 * 模型不支持多模态输入异常.
 */
class LLMModalityNotSupportedException extends LLMModelException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 4;

    /**
     * 创建一个新的多模态不支持异常实例.
     */
    public function __construct(string $message = ErrorMessage::MULTIMODAL_NOT_SUPPORTED, ?Throwable $previous = null, ?string $model = null)
    {
        parent::__construct($message, self::ERROR_CODE, $previous, 0, $model, 400);
    }
}

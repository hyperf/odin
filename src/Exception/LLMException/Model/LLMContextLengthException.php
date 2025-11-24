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
 * 上下文长度超出限制异常.
 */
class LLMContextLengthException extends LLMModelException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 2;

    /**
     * 当前上下文长度.
     */
    protected ?int $currentLength = null;

    /**
     * 最大上下文长度.
     */
    protected ?int $maxLength = null;

    /**
     * Create a new context length exception instance.
     */
    public function __construct(
        string $message = ErrorMessage::CONTEXT_LENGTH,
        ?Throwable $previous = null,
        ?string $model = null,
        ?int $currentLength = null,
        ?int $maxLength = null,
        int $statusCode = 400
    ) {
        $this->currentLength = $currentLength;
        $this->maxLength = $maxLength;

        if ($currentLength !== null && $maxLength !== null) {
            $message = sprintf('%s, current length: %d, max limit: %d', $message, $currentLength, $maxLength);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $model, $statusCode);
    }

    /**
     * 获取当前上下文长度.
     */
    public function getCurrentLength(): ?int
    {
        return $this->currentLength;
    }

    /**
     * 获取最大上下文长度.
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }
}

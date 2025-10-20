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
 * 嵌入输入过大异常.
 *
 * 当嵌入请求的输入内容超出模型处理能力时抛出此异常
 */
class LLMEmbeddingInputTooLargeException extends LLMModelException
{
    private ?int $inputLength = null;

    private ?int $maxInputLength = null;

    /**
     * @param string $message 异常消息
     * @param null|Throwable $previous 前一个异常
     * @param null|string $model 模型名称
     * @param null|int $inputLength 输入内容长度
     * @param null|int $maxInputLength 最大输入长度限制
     * @param int $statusCode HTTP状态码
     */
    public function __construct(
        string $message = ErrorMessage::EMBEDDING_INPUT_TOO_LARGE,
        ?Throwable $previous = null,
        ?string $model = null,
        ?int $inputLength = null,
        ?int $maxInputLength = null,
        int $statusCode = 400
    ) {
        $this->inputLength = $inputLength;
        $this->maxInputLength = $maxInputLength;

        parent::__construct($message, 2, $previous, 4007, $model, $statusCode);
    }

    /**
     * 获取输入内容长度.
     */
    public function getInputLength(): ?int
    {
        return $this->inputLength;
    }

    /**
     * 获取最大输入长度限制.
     */
    public function getMaxInputLength(): ?int
    {
        return $this->maxInputLength;
    }

    /**
     * 获取用户友好的建议信息.
     */
    public function getSuggestion(): string
    {
        $suggestions = [
            'Consider splitting the input text into smaller chunks for processing',
            'You can use a TextSplitter tool to split the text',
            'Consider removing unnecessary multimedia content or formatting tags',
        ];

        if ($this->inputLength && $this->maxInputLength) {
            array_unshift($suggestions, sprintf(
                'Current input length: %d, max limit: %d',
                $this->inputLength,
                $this->maxInputLength
            ));
        }

        return implode('; ', $suggestions);
    }
}

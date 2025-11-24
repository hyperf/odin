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
 * 内容被过滤异常.
 */
class LLMContentFilterException extends LLMModelException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 1;

    /**
     * 触发过滤的内容标签.
     */
    protected ?array $contentLabels = null;

    /**
     * 创建一个新的内容过滤异常实例.
     */
    public function __construct(
        string $message = ErrorMessage::CONTENT_FILTER,
        ?Throwable $previous = null,
        ?string $model = null,
        ?array $contentLabels = null,
        int $statusCode = 400
    ) {
        $this->contentLabels = $contentLabels;

        if (! empty($contentLabels)) {
            $labelsStr = implode(', ', $contentLabels);
            $message = sprintf('%s, reasons: %s', $message, $labelsStr);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $model, $statusCode);
    }

    /**
     * 获取触发过滤的内容标签.
     */
    public function getContentLabels(): ?array
    {
        return $this->contentLabels;
    }
}

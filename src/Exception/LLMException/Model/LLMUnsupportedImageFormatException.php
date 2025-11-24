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
 * Exception thrown when an unsupported image format is used in vision requests.
 *
 * 当在视觉理解请求中使用不支持的图片格式时抛出的异常。
 */
class LLMUnsupportedImageFormatException extends LLMModelException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 12;

    /**
     * The unsupported file extension.
     */
    protected ?string $fileExtension = null;

    /**
     * The image URL that caused the error.
     */
    protected ?string $imageUrl = null;

    /**
     * The unsupported content type.
     */
    protected ?string $contentType = null;

    /**
     * Create a new unsupported image format exception.
     *
     * @param string $message Exception message
     * @param null|Throwable $previous Previous exception
     * @param null|string $fileExtension The unsupported file extension
     * @param null|string $imageUrl The image URL that caused the error
     * @param null|string $contentType The unsupported content type
     * @param int $statusCode HTTP status code
     */
    public function __construct(
        string $message = ErrorMessage::UNSUPPORTED_IMAGE_FORMAT,
        ?Throwable $previous = null,
        ?string $fileExtension = null,
        ?string $imageUrl = null,
        ?string $contentType = null,
        int $statusCode = 400
    ) {
        $this->fileExtension = $fileExtension;
        $this->imageUrl = $imageUrl;
        $this->contentType = $contentType;

        parent::__construct($message, self::ERROR_CODE, $previous, 0, null, $statusCode);
    }

    /**
     * Get the unsupported file extension.
     */
    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    /**
     * Get the image URL that caused the error.
     */
    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /**
     * Get the unsupported content type.
     */
    public function getContentType(): ?string
    {
        return $this->contentType;
    }
}

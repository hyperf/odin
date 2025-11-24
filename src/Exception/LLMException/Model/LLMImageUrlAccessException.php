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

use Hyperf\Odin\Exception\LLMException\ErrorCode;
use Hyperf\Odin\Exception\LLMException\ErrorMessage;
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use Throwable;

/**
 * 多模态图片URL不可访问异常.
 */
class LLMImageUrlAccessException extends LLMModelException
{
    /**
     * 错误码，基于模型错误基数.
     */
    private const ERROR_CODE = 6; // 4000 + 6 = 4006

    /**
     * 不可访问的图片URL.
     */
    protected ?string $imageUrl = null;

    /**
     * 创建一个新的图片URL不可访问异常实例.
     */
    public function __construct(
        string $message = ErrorMessage::IMAGE_URL_ACCESS,
        ?Throwable $previous = null,
        ?string $model = null,
        ?string $imageUrl = null,
        int $statusCode = 400
    ) {
        $this->imageUrl = $imageUrl;

        if (! empty($imageUrl)) {
            $message = sprintf('%s, image URL: %s', $message, $imageUrl);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, ErrorCode::MODEL_IMAGE_URL_ACCESS_ERROR, $model, $statusCode);
    }

    /**
     * 获取不可访问的图片URL.
     */
    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }
}

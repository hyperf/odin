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

namespace Hyperf\Odin\Utils;

use Hyperf\Odin\Exception\LLMException\Model\LLMUnsupportedImageFormatException;

/**
 * Simple image format validator for vision understanding requests.
 *
 * 视觉理解请求的简单图片格式验证器。
 */
class ImageFormatValidator
{
    /**
     * Supported image file extensions.
     *
     * @var string[]
     */
    private static array $supportedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif',
        'ico', 'dib', 'icns', 'sgi', 'j2c', 'j2k', 'jp2', 'jpc', 'jpf', 'jpx',
    ];

    /**
     * Validate image URL format.
     * Only validates URLs that have file extensions.
     *
     * 验证图片URL格式。
     * 只验证有文件扩展名的URL。
     *
     * @param string $imageUrl The image URL to validate
     * @throws LLMUnsupportedImageFormatException When extension exists but is not supported
     */
    public static function validateImageUrl(string $imageUrl): void
    {
        // Skip validation if it's a data URL (Base64)
        if (str_starts_with($imageUrl, 'data:')) {
            return;
        }

        // Extract file extension from URL
        $urlPath = parse_url($imageUrl, PHP_URL_PATH);
        if (! $urlPath) {
            // Cannot parse URL path, but don't throw error
            return;
        }

        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        // If no extension, don't throw error
        if (empty($extension)) {
            return;
        }

        // If extension exists but not supported, throw error
        if (! in_array($extension, self::$supportedExtensions, true)) {
            throw new LLMUnsupportedImageFormatException(
                sprintf('不支持的图片格式: .%s', $extension),
                null,
                $extension,
                $imageUrl
            );
        }
    }

    /**
     * Get all supported file extensions.
     *
     * 获取所有支持的文件扩展名。
     *
     * @return string[] Array of supported file extensions
     */
    public static function getSupportedExtensions(): array
    {
        return self::$supportedExtensions;
    }
}

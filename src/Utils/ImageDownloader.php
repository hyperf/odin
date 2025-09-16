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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;

/**
 * Image downloader utility for downloading remote images.
 *
 * 图片下载工具类，用于下载远程图片。
 */
class ImageDownloader
{
    /**
     * Maximum image file size (10MB).
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * Connection timeout in seconds.
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * Read timeout in seconds.
     */
    private const READ_TIMEOUT = 30;

    /**
     * Download image from URL and convert to base64 data URL.
     *
     * @param string $imageUrl HTTP(S) image URL
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @return string Base64 data URL (data:image/xxx;base64,...)
     * @throws LLMInvalidRequestException
     */
    public static function downloadAndConvertToBase64(string $imageUrl, int $maxFileSize = self::MAX_FILE_SIZE): string
    {
        // Try different download strategies
        $strategies = [
            'standard' => fn () => self::downloadWithStrategy($imageUrl, $maxFileSize, 'standard'),
            'simple' => fn () => self::downloadWithStrategy($imageUrl, $maxFileSize, 'simple'),
            'mobile' => fn () => self::downloadWithStrategy($imageUrl, $maxFileSize, 'mobile'),
        ];

        $lastException = null;

        foreach ($strategies as $strategyName => $downloadFn) {
            try {
                return $downloadFn();
            } catch (LLMInvalidRequestException $e) {
                $lastException = $e;
                // Continue to next strategy
                continue;
            }
        }

        // If all strategies failed, throw the last exception
        throw $lastException ?? new LLMInvalidRequestException('所有下载策略都失败了');
    }

    /**
     * Detect image MIME type from binary data using PHP 8.1 syntax.
     *
     * @param string $imageData Binary image data
     * @return null|string MIME type (e.g., 'image/jpeg', 'image/png') or null if unknown
     */
    public static function detectImageMimeType(string $imageData): ?string
    {
        // Check minimum data length
        if (strlen($imageData) < 8) {
            return null;
        }

        return match (true) {
            // JPEG - starts with 0xFF 0xD8 0xFF
            str_starts_with($imageData, "\xFF\xD8\xFF") => 'image/jpeg',

            // PNG - starts with specific 8-byte signature
            str_starts_with($imageData, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") => 'image/png',

            // GIF87a or GIF89a
            str_starts_with($imageData, 'GIF87a') || str_starts_with($imageData, 'GIF89a') => 'image/gif',

            // WebP - RIFF container with WEBP type
            strlen($imageData) >= 12
            && str_starts_with($imageData, 'RIFF')
            && str_starts_with(substr($imageData, 8), 'WEBP') => 'image/webp',

            // BMP - starts with 'BM'
            str_starts_with($imageData, 'BM') => 'image/bmp',

            // TIFF (little endian) - 'II' followed by 42
            strlen($imageData) >= 4 && str_starts_with($imageData, "II\x2A\x00") => 'image/tiff',

            // TIFF (big endian) - 'MM' followed by 42
            strlen($imageData) >= 4 && str_starts_with($imageData, "MM\x00\x2A") => 'image/tiff',

            // Unknown format
            default => null,
        };
    }

    /**
     * Check if URL is a remote image URL (HTTP/HTTPS).
     *
     * @param string $url URL to check
     * @return bool True if it's a remote image URL
     */
    public static function isRemoteImageUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Check if URL is a base64 data URL.
     *
     * @param string $url URL to check
     * @return bool True if it's a base64 data URL
     */
    public static function isBase64DataUrl(string $url): bool
    {
        return str_starts_with($url, 'data:image/') && str_contains($url, ';base64,');
    }

    /**
     * Get maximum file size limit.
     *
     * @return int Maximum file size in bytes
     */
    public static function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get maximum file size limit in human readable format.
     *
     * @return string Maximum file size (e.g., "10MB")
     */
    public static function getMaxFileSizeFormatted(): string
    {
        return self::formatFileSize(self::MAX_FILE_SIZE);
    }

    /**
     * Format file size in human readable format.
     *
     * @param int $bytes File size in bytes
     * @return string Formatted file size (e.g., "10MB", "512KB", "1.5GB")
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $factor), 1) . $units[$factor];
    }

    /**
     * Download image with specific strategy.
     *
     * @param string $imageUrl HTTP(S) image URL
     * @param int $maxFileSize Maximum file size in bytes
     * @param string $strategy Download strategy
     * @return string Base64 data URL
     * @throws LLMInvalidRequestException
     */
    private static function downloadWithStrategy(string $imageUrl, int $maxFileSize, string $strategy): string
    {
        // Validate URL format and protocol using PHP 8.1 syntax
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            throw new LLMInvalidRequestException('无效的图片URL格式');
        }

        if (! str_starts_with($imageUrl, 'http://') && ! str_starts_with($imageUrl, 'https://')) {
            throw new LLMInvalidRequestException('只支持HTTP/HTTPS协议的图片URL');
        }

        // Get client configuration based on strategy
        $clientConfig = self::getClientConfig($strategy);

        $result = null;

        try {
            $client = new Client($clientConfig);

            // Download image directly to memory
            $response = $client->get($imageUrl, [
                'stream' => false, // Download entire response to memory
                'progress' => function ($downloadTotal, $downloadedBytes) use ($maxFileSize, $strategy) {
                    if ($downloadedBytes > $maxFileSize) {
                        $limitFormatted = self::formatFileSize($maxFileSize);
                        throw new LLMInvalidRequestException("图片文件过大，超过{$limitFormatted}限制 (策略: {$strategy})");
                    }
                },
            ]);

            // Get response information for debugging
            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaderLine('Content-Type');
            $contentLength = $response->getHeaderLine('Content-Length');

            // Get the actual image data
            $imageData = $response->getBody()->getContents();
            $actualSize = strlen($imageData);

            if ($actualSize > $maxFileSize) {
                $limitFormatted = self::formatFileSize($maxFileSize);
                throw new LLMInvalidRequestException("图片文件过大，超过{$limitFormatted}限制 (策略: {$strategy})");
            }

            if ($actualSize === 0) {
                $errorDetails = [
                    "策略: {$strategy}",
                    "HTTP状态: {$statusCode}",
                    'Content-Type: ' . ($contentType ?: 'unknown'),
                    'Content-Length: ' . ($contentLength ?: 'unknown'),
                    "实际大小: {$actualSize}",
                    "URL: {$imageUrl}",
                ];
                $errorMessage = '下载的图片文件为空 (' . implode(', ', $errorDetails) . ')';
                throw new LLMInvalidRequestException($errorMessage);
            }

            // Detect image format
            $mimeType = self::detectImageMimeType($imageData);
            if (! $mimeType) {
                throw new LLMInvalidRequestException("不支持的图片格式或文件已损坏 (策略: {$strategy})");
            }

            // Convert to base64 data URL
            $base64Data = base64_encode($imageData);
            $result = "data:{$mimeType};base64,{$base64Data}";
        } catch (RequestException $e) {
            throw new LLMInvalidRequestException("下载图片失败 (策略: {$strategy}): " . $e->getMessage());
        }

        // This should never be reached if exceptions are properly thrown above
        return $result ?? throw new LLMInvalidRequestException('下载过程中发生未知错误');
    }

    /**
     * Get HTTP client configuration for different download strategies.
     *
     * @param string $strategy Download strategy ('standard', 'simple', 'mobile')
     * @return array Client configuration
     */
    private static function getClientConfig(string $strategy): array
    {
        $baseConfig = [
            'timeout' => self::READ_TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ];

        return match ($strategy) {
            'standard' => array_merge($baseConfig, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Referer' => 'https://www.google.com/',
                ],
                'verify' => false,
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                    'referer' => true,
                    'track_redirects' => true,
                ],
            ]),

            'simple' => array_merge($baseConfig, [
                'headers' => [
                    'User-Agent' => 'Odin-ImageDownloader/1.0',
                    'Accept' => 'image/*',
                ],
                'verify' => true,
                'allow_redirects' => true,
            ]),

            'mobile' => array_merge($baseConfig, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Accept-Language' => 'zh-CN,zh;q=0.9',
                ],
                'verify' => false,
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                ],
            ]),

            default => $baseConfig,
        };
    }
}

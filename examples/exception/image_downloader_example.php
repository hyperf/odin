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
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Utils\ImageDownloader;

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== ImageDownloader Utility Example ===\n";
echo "=== 图片下载工具示例 ===\n\n";

// Test URLs
$testUrls = [
    // Valid remote image URLs (using placeholder URLs for testing)
    'https://via.placeholder.com/300x200.jpg' => '✅ 期望成功 (小图片)',
    'https://httpbin.org/image/jpeg' => '✅ 期望成功 (JPEG)',
    'https://httpbin.org/image/png' => '✅ 期望成功 (PNG)',

    // Base64 data URL (should be recognized but not downloaded)
    'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEA...' => '✅ 期望识别为Base64',

    // Invalid URLs
    'ftp://example.com/image.jpg' => '❌ 期望失败 (不支持的协议)',
    'invalid-url' => '❌ 期望失败 (无效URL)',
    'https://httpbin.org/status/404' => '❌ 期望失败 (404错误)',
];

echo "🔍 Testing ImageDownloader utility:\n";
echo "🔍 测试ImageDownloader工具：\n";
echo '文件大小限制: ' . ImageDownloader::getMaxFileSizeFormatted() . "\n\n";

foreach ($testUrls as $url => $expected) {
    $displayUrl = strlen($url) > 60 ? substr($url, 0, 57) . '...' : $url;
    echo "Testing: {$displayUrl}\n";
    echo "Expected: {$expected}\n";

    try {
        // Check URL type
        if (ImageDownloader::isRemoteImageUrl($url)) {
            echo "  Type: Remote URL\n";

            // Try to download and convert
            $base64Url = ImageDownloader::downloadAndConvertToBase64($url);

            // Check result
            if (ImageDownloader::isBase64DataUrl($base64Url)) {
                echo "  Result: ✅ PASSED - Successfully downloaded and converted to base64\n";
                echo '  Base64 URL length: ' . strlen($base64Url) . " chars\n";

                // Show MIME type
                preg_match('/data:(image\/[^;]+)/', $base64Url, $matches);
                $mimeType = $matches[1] ?? 'unknown';
                echo "  Detected MIME type: {$mimeType}\n";
            } else {
                echo "  Result: ❌ FAILED - Invalid base64 format returned\n";
            }
        } elseif (ImageDownloader::isBase64DataUrl($url)) {
            echo "  Type: Base64 Data URL\n";
            echo "  Result: ✅ PASSED - Already in base64 format\n";
        } else {
            echo "  Type: Invalid URL\n";
            echo "  Result: ❌ FAILED - Invalid URL format\n";
        }
    } catch (LLMInvalidRequestException $e) {
        echo '  Result: ❌ FAILED - ' . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo '  Result: ⚠️  ERROR - ' . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Test image format detection
echo "🧪 Testing image format detection:\n";
echo "🧪 测试图片格式检测：\n\n";

$testBinaryData = [
    'JPEG header' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01",
    'PNG header' => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A\x00\x00\x00\x0D",
    'GIF87a header' => "GIF87a\x01\x00\x01\x00\x00\x00\x00\x00",
    'GIF89a header' => "GIF89a\x01\x00\x01\x00\x00\x00\x00\x00",
    'WebP header' => "RIFF\x1A\x00\x00\x00WEBPVP8 \x0E\x00",
    'BMP header' => "BM\x1A\x00\x00\x00\x00\x00\x00\x00\x00\x00",
    'TIFF LE header' => "II\x2A\x00\x08\x00\x00\x00",
    'TIFF BE header' => "MM\x00\x2A\x00\x00\x00\x08",
    'Invalid data' => 'This is not image data at all',
];

foreach ($testBinaryData as $name => $binaryData) {
    $mimeType = ImageDownloader::detectImageMimeType($binaryData);
    $result = $mimeType ? "✅ {$mimeType}" : '❌ Unknown format';
    echo "  {$name}: {$result}\n";
}

echo "\n💡 Utility Features / 工具特性:\n";
echo "  ✅ 支持HTTP/HTTPS图片URL下载\n";
echo "  ✅ 自动检测图片格式 (JPEG, PNG, GIF, WebP, BMP, TIFF)\n";
echo "  ✅ 转换为标准Base64 Data URL格式\n";
echo '  ✅ 文件大小限制: ' . ImageDownloader::getMaxFileSizeFormatted() . "\n";
echo "  ✅ 超时保护: 连接10秒，读取30秒\n";
echo "  ✅ 完整的错误处理和验证\n\n";

echo "🔧 Integration with AWS Bedrock:\n";
echo "  1. 检测远程图片URL\n";
echo "  2. 自动下载并转换为Base64格式\n";
echo "  3. 继续使用原有的Base64处理逻辑\n";
echo "  4. 无缝集成，保持向后兼容\n";

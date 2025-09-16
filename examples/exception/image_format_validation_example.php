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
use Hyperf\Odin\Exception\LLMException\Model\LLMUnsupportedImageFormatException;
use Hyperf\Odin\Utils\ImageFormatValidator;

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== Simple Image Format Validation Example ===\n";
echo "=== 简单图片格式验证示例 ===\n\n";

// Test cases for URL validation
$testUrls = [
    // Valid formats
    'https://example.com/image.jpg' => '✅ 期望成功 (有效扩展名)',
    'https://example.com/image.png' => '✅ 期望成功 (有效扩展名)',
    'https://example.com/image.webp' => '✅ 期望成功 (有效扩展名)',

    // Invalid formats (have extension but not supported)
    'https://example.com/document.pdf' => '❌ 期望失败 (不支持的扩展名)',
    'https://example.com/video.mp4' => '❌ 期望失败 (不支持的扩展名)',
    'https://example.com/document.docx' => '❌ 期望失败 (不支持的扩展名)',

    // No extension - should pass
    'https://example.com/image' => '✅ 期望成功 (无扩展名)',
    'https://example.com/api/image/123' => '✅ 期望成功 (无扩展名)',
    'https://cdn.example.com/images?id=123' => '✅ 期望成功 (无扩展名)',

    // Base64 - should pass
    'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEA...' => '✅ 期望成功 (Base64)',
];

echo "🔍 Testing simplified URL validation:\n";
echo "🔍 测试简化的URL验证：\n";
echo "规则：只有URL有扩展名且不在支持列表中时才报错\n\n";

foreach ($testUrls as $url => $expected) {
    $displayUrl = strlen($url) > 60 ? substr($url, 0, 57) . '...' : $url;
    echo "Testing: {$displayUrl}\n";
    echo "Expected: {$expected}\n";

    try {
        ImageFormatValidator::validateImageUrl($url);
        echo "Result: ✅ PASSED - Validation passed\n";
    } catch (LLMUnsupportedImageFormatException $e) {
        echo 'Result: ❌ FAILED - ' . $e->getMessage() . "\n";
        if ($e->getFileExtension()) {
            echo '  Extension: ' . $e->getFileExtension() . "\n";
        }
    } catch (Exception $e) {
        echo 'Result: ⚠️  ERROR - ' . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Display supported formats
echo "📋 Supported Image Extensions:\n";
echo "📋 支持的图片扩展名：\n\n";

$supportedExtensions = ImageFormatValidator::getSupportedExtensions();

echo "支持的扩展名:\n";
foreach (array_chunk($supportedExtensions, 8) as $chunk) {
    echo '  ' . implode(', ', array_map(fn ($ext) => ".{$ext}", $chunk)) . "\n";
}
echo "\n";

echo "💡 Validation Rules / 验证规则:\n";
echo "  ✅ 无扩展名的URL → 通过验证\n";
echo "  ✅ Base64格式(data:...) → 通过验证\n";
echo "  ✅ 支持的扩展名 → 通过验证\n";
echo "  ❌ 不支持的扩展名 → 验证失败\n";
echo "  ❌ 无法解析的URL → 通过验证(不报错)\n";

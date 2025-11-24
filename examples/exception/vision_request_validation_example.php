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
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Utils\VisionMessageValidator;

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== Simple Vision Request Validation Example ===\n";
echo "=== 简单视觉理解请求验证示例 ===\n\n";

// Test case 1: Valid vision message with supported image format
echo "📝 Test Case 1: Valid image format / 有效的图片格式\n";
try {
    $validMessage = (new UserMessage('Please analyze this image'))
        ->addContent(UserMessageContent::text('Please analyze this image'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/image.jpg'));

    VisionMessageValidator::validateUserMessage($validMessage);
    echo "✅ PASSED - Valid image format accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

// Test case 2: Invalid vision message with unsupported image format
echo "📝 Test Case 2: Invalid image format / 无效的图片格式\n";
try {
    $invalidMessage = (new UserMessage('Please analyze this document'))
        ->addContent(UserMessageContent::text('Please analyze this document'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/document.pdf'));

    VisionMessageValidator::validateUserMessage($invalidMessage);
    echo "❌ FAILED - Should have rejected invalid format\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo "✅ PASSED - Invalid image format correctly rejected\n";
    echo '  Error: ' . $e->getMessage() . "\n";
    echo '  Extension: ' . $e->getFileExtension() . "\n";
}
echo "\n";

// Test case 3: URL without extension (should pass)
echo "📝 Test Case 3: URL without extension / 无扩展名URL\n";
try {
    $noExtMessage = (new UserMessage('Analyze this image'))
        ->addContent(UserMessageContent::text('Analyze this image'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/api/image/123'));

    VisionMessageValidator::validateUserMessage($noExtMessage);
    echo "✅ PASSED - URL without extension accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

// Test case 4: Base64 image (should pass)
echo "📝 Test Case 4: Base64 image / Base64图片\n";
try {
    $base64Message = (new UserMessage('Analyze this Base64 image'))
        ->addContent(UserMessageContent::text('Analyze this Base64 image'))
        ->addContent(UserMessageContent::imageUrl('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg=='));

    VisionMessageValidator::validateUserMessage($base64Message);
    echo "✅ PASSED - Base64 image accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

// Test case 5: Text-only message (should pass)
echo "📝 Test Case 5: Text-only message / 纯文本消息\n";
try {
    $textMessage = new UserMessage('This is just a text message without images');

    VisionMessageValidator::validateUserMessage($textMessage);
    echo "✅ PASSED - Text-only message accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

echo "💡 Validation Rules / 验证规则:\n";
echo "  ✅ 无扩展名的URL → 通过验证\n";
echo "  ✅ Base64格式(data:...) → 通过验证\n";
echo "  ✅ 支持的扩展名 → 通过验证\n";
echo "  ❌ 不支持的扩展名 → 验证失败\n";
echo "  ✅ 纯文本消息 → 通过验证\n\n";

echo "🔧 Integration Tips / 集成建议:\n";
echo "1. 在处理视觉理解请求前调用验证器\n";
echo "2. 只有URL带有不支持的扩展名时才会报错\n";
echo "3. 其他情况（无扩展名、Base64等）都会通过验证\n";

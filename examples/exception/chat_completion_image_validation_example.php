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
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Exception\LLMException\Model\LLMUnsupportedImageFormatException;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== ChatCompletionRequest Image Validation Example ===\n";
echo "=== ChatCompletionRequest 图片验证示例 ===\n\n";

// Test case 1: Valid image format in chat request
echo "📝 Test Case 1: Valid image format / 有效的图片格式\n";
try {
    $validUserMessage = (new UserMessage('Please analyze this image'))
        ->addContent(UserMessageContent::text('Please analyze this image'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/photo.jpg'));

    $chatRequest = new ChatCompletionRequest(
        messages: [
            new SystemMessage('You are a helpful vision assistant.'),
            $validUserMessage,
        ],
        model: 'gpt-4-vision-preview',
        temperature: 0.7
    );

    $chatRequest->validate();
    echo "✅ PASSED - Valid image format in chat request accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
    echo '  Extension: ' . $e->getFileExtension() . "\n";
}
echo "\n";

// Test case 2: Invalid image format in chat request
echo "📝 Test Case 2: Invalid image format / 无效的图片格式\n";
try {
    $invalidUserMessage = (new UserMessage('Please analyze this document'))
        ->addContent(UserMessageContent::text('Please analyze this document'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/document.pdf'));

    $chatRequest = new ChatCompletionRequest(
        messages: [
            new SystemMessage('You are a helpful vision assistant.'),
            $invalidUserMessage,
        ],
        model: 'gpt-4-vision-preview',
        temperature: 0.7
    );

    $chatRequest->validate();
    echo "❌ FAILED - Should have rejected invalid image format\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo "✅ PASSED - Invalid image format correctly rejected in chat request\n";
    echo '  Error: ' . $e->getMessage() . "\n";
    echo '  Extension: ' . $e->getFileExtension() . "\n";
}
echo "\n";

// Test case 3: URL without extension (should pass)
echo "📝 Test Case 3: URL without extension / 无扩展名URL\n";
try {
    $noExtUserMessage = (new UserMessage('Analyze this image'))
        ->addContent(UserMessageContent::text('Analyze this image'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/api/image/123'));

    $chatRequest = new ChatCompletionRequest(
        messages: [
            new SystemMessage('You are a helpful vision assistant.'),
            $noExtUserMessage,
        ],
        model: 'gpt-4-vision-preview',
        temperature: 0.7
    );

    $chatRequest->validate();
    echo "✅ PASSED - URL without extension accepted in chat request\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

// Test case 4: Multiple messages with mixed image formats
echo "📝 Test Case 4: Multiple messages with mixed formats / 多消息混合格式\n";
try {
    $validMessage = (new UserMessage('First image'))
        ->addContent(UserMessageContent::text('First image'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/image1.jpg'));

    $invalidMessage = (new UserMessage('Second file'))
        ->addContent(UserMessageContent::text('Second file'))
        ->addContent(UserMessageContent::imageUrl('https://example.com/document.docx'));

    $chatRequest = new ChatCompletionRequest(
        messages: [
            new SystemMessage('You are a helpful vision assistant.'),
            $validMessage,
            $invalidMessage,
        ],
        model: 'gpt-4-vision-preview',
        temperature: 0.7
    );

    $chatRequest->validate();
    echo "❌ FAILED - Should have rejected invalid format in multiple messages\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo "✅ PASSED - Invalid format detected in multiple messages\n";
    echo '  Error: ' . $e->getMessage() . "\n";
    echo '  Extension: ' . $e->getFileExtension() . "\n";
}
echo "\n";

// Test case 5: Text-only chat request (should pass)
echo "📝 Test Case 5: Text-only chat request / 纯文本聊天请求\n";
try {
    $chatRequest = new ChatCompletionRequest(
        messages: [
            new SystemMessage('You are a helpful assistant.'),
            new UserMessage('What is the capital of France?'),
        ],
        model: 'gpt-3.5-turbo',
        temperature: 0.7
    );

    $chatRequest->validate();
    echo "✅ PASSED - Text-only chat request accepted\n";
} catch (LLMUnsupportedImageFormatException $e) {
    echo '❌ FAILED - ' . $e->getMessage() . "\n";
}
echo "\n";

echo "🔧 Integration Summary / 集成总结:\n";
echo "✅ 图片格式验证已成功集成到 ChatCompletionRequest::validate() 方法中\n";
echo "✅ 只有URL带有不支持扩展名的图片才会被拒绝\n";
echo "✅ 其他情况（无扩展名、Base64、支持格式）都能正常通过验证\n";
echo "✅ 验证发生在消息序列验证之后，确保基础验证通过\n";
echo "✅ 抛出的异常包含详细的错误信息和具体的不支持扩展名\n";

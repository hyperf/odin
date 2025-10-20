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
/**
 * Example: Handling Errors in Proxy Scenarios.
 *
 * This example demonstrates how Odin properly handles errors when services
 * are proxied through multiple layers. The error detection mechanism can
 * recognize errors from downstream Odin services regardless of the response
 * format (flat or nested).
 *
 * Supported Error Response Formats:
 * 1. OpenAI format (nested): {"error": {"message": "...", "code": 4002}}
 * 2. Flat format: {"code": 4002, "message": "..."}
 *
 * The system will:
 * - Extract error messages from response body
 * - Match Chinese and English error messages
 * - Properly map errors to specific exception types
 * - Preserve error details across proxy layers
 */

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Exception\LLMException\Model\LLMContextLengthException;

require_once __DIR__ . '/../../vendor/autoload.php';

// Example 1: Handling OpenAI-style nested error response
echo "Example 1: OpenAI-style nested error response\n";
echo str_repeat('=', 60) . "\n";

$nestedErrorResponse = json_encode([
    'error' => [
        'message' => '上下文长度超出模型限制',
        'code' => 4002,
        'request_id' => '838816451070042112',
    ],
]);

$request = new Request('POST', 'https://proxy-service.example.com/v1/chat/completions');
$response = new Response(400, [], $nestedErrorResponse);
$exception = new RequestException('Client error', $request, $response);

$errorHandler = new LLMErrorHandler();
$mappedException = $errorHandler->handle($exception);

echo 'Exception Type: ' . get_class($mappedException) . "\n";
echo 'Error Message: ' . $mappedException->getMessage() . "\n";
echo 'Error Code: ' . $mappedException->getErrorCode() . "\n";

if ($mappedException instanceof LLMContextLengthException) {
    echo 'Current Length: ' . ($mappedException->getCurrentLength() ?? 'N/A') . "\n";
    echo 'Max Length: ' . ($mappedException->getMaxLength() ?? 'N/A') . "\n";
}
echo "\n";

// Example 2: Handling flat error response
echo "Example 2: Flat error response\n";
echo str_repeat('=', 60) . "\n";

$flatErrorResponse = json_encode([
    'code' => 4002,
    'message' => '上下文长度超出模型限制',
]);

$request = new Request('POST', 'https://proxy-service.example.com/v1/chat/completions');
$response = new Response(400, [], $flatErrorResponse);
$exception = new RequestException('Client error', $request, $response);

$mappedException = $errorHandler->handle($exception);

echo 'Exception Type: ' . get_class($mappedException) . "\n";
echo 'Error Message: ' . $mappedException->getMessage() . "\n";
echo 'Error Code: ' . $mappedException->getErrorCode() . "\n";
echo "\n";

// Example 3: Handling error with detailed context information
echo "Example 3: Error with detailed context information\n";
echo str_repeat('=', 60) . "\n";

$detailedErrorResponse = json_encode([
    'error' => [
        'message' => '上下文长度超出模型限制，当前长度: 8000，最大限制: 4096',
        'code' => 4002,
        'type' => 'context_length_exceeded',
        'request_id' => '838816451070042116',
    ],
]);

$request = new Request('POST', 'https://proxy-service.example.com/v1/chat/completions');
$response = new Response(400, [], $detailedErrorResponse);
$exception = new RequestException('Downstream error', $request, $response);

$mappedException = $errorHandler->handle($exception);

echo 'Exception Type: ' . get_class($mappedException) . "\n";
echo 'Error Message: ' . $mappedException->getMessage() . "\n";
echo 'Error Code: ' . $mappedException->getErrorCode() . "\n";

if ($mappedException instanceof LLMContextLengthException) {
    echo 'Current Length: ' . ($mappedException->getCurrentLength() ?? 'N/A') . "\n";
    echo 'Max Length: ' . ($mappedException->getMaxLength() ?? 'N/A') . "\n";
}
echo "\n";

// Example 4: Generating error report for logging/debugging
echo "Example 4: Generating error report\n";
echo str_repeat('=', 60) . "\n";

$errorReport = $errorHandler->generateErrorReport($mappedException);
echo "Error Report:\n";
echo json_encode($errorReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "\n";

// Example 5: Demonstrating various Chinese error messages
echo "Example 5: Various Chinese error messages\n";
echo str_repeat('=', 60) . "\n";

$chineseErrors = [
    ['message' => 'API请求频率超出限制', 'status' => 429],
    ['message' => '内容被系统安全过滤', 'status' => 400],
    ['message' => 'API密钥无效或已过期', 'status' => 401],
];

foreach ($chineseErrors as $error) {
    $errorResponse = json_encode([
        'error' => [
            'message' => $error['message'],
            'code' => 4000,
        ],
    ]);

    $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
    $response = new Response($error['status'], [], $errorResponse);
    $exception = new RequestException('Error', $request, $response);

    $mappedException = $errorHandler->handle($exception);

    echo "Message: {$error['message']}\n";
    echo '  → Mapped to: ' . get_class($mappedException) . "\n";
    echo '  → Error Code: ' . $mappedException->getErrorCode() . "\n\n";
}

echo "\nKey Features:\n";
echo "- Supports both OpenAI-style nested and flat error formats\n";
echo "- Recognizes Chinese and English error messages\n";
echo "- Extracts detailed error information (lengths, retry times, etc.)\n";
echo "- Works seamlessly with multiple proxy layers\n";
echo "- Maintains error context across service boundaries\n";

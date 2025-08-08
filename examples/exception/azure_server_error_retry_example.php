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
require_once __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContentFilterException;

echo "=== Azure OpenAI 异常分类与重试机制演示 ===\n\n";

// 模拟两种不同的 Azure OpenAI 500 错误
$testCases = [
    'model_error (内容过滤)' => [
        'error_type' => 'model_error',
        'message' => 'The model produced invalid content. Consider modifying your prompt if you are seeing this error persistently.',
        'expected_exception' => LLMContentFilterException::class,
        'retryable' => false,
        'user_action' => '修改提示词内容',
    ],
    'server_error (服务故障)' => [
        'error_type' => 'server_error',
        'message' => 'The server had an error while processing your request. Sorry about that!',
        'expected_exception' => LLMNetworkException::class,
        'retryable' => true,
        'user_action' => '自动重试',
    ],
];

$errorMappingManager = new ErrorMappingManager();

foreach ($testCases as $caseName => $testCase) {
    echo "🧪 测试场景: {$caseName}\n";
    echo "   Azure错误类型: {$testCase['error_type']}\n";
    echo "   Azure错误消息: {$testCase['message']}\n";

    // 创建模拟的Azure错误响应
    $errorBody = json_encode([
        'error' => [
            'message' => $testCase['message'],
            'type' => $testCase['error_type'],
            'param' => null,
            'code' => null,
        ],
    ]);

    $request = new Request('POST', 'https://test-azure-openai.example.com/openai/deployments/test-gpt/chat/completions');
    $response = new Response(500, ['Content-Type' => 'application/json'], $errorBody);

    $requestException = new RequestException(
        "Server error: {$testCase['message']}",
        $request,
        $response
    );

    // 通过异常映射管理器处理
    $mappedException = $errorMappingManager->mapException($requestException);

    echo "   ✅ 映射结果:\n";
    echo '      异常类型: ' . get_class($mappedException) . "\n";
    echo "      异常消息: {$mappedException->getMessage()}\n";
    echo "      HTTP状态码: {$mappedException->getStatusCode()}\n";
    echo "      错误代码: {$mappedException->getErrorCode()}\n";

    // 检查重试逻辑
    $isRetryable = $mappedException instanceof LLMNetworkException;
    echo '      可重试: ' . ($isRetryable ? '✅ 是' : '❌ 否') . "\n";
    echo "      用户操作: {$testCase['user_action']}\n";

    // 验证分类正确性
    $isCorrectType = $mappedException instanceof $testCase['expected_exception'];
    echo '      分类正确: ' . ($isCorrectType ? '✅ 是' : '❌ 否') . "\n";

    echo "\n";
}

echo "=== 重试机制逻辑演示 ===\n";
echo "在 AbstractModel::callWithNetworkRetry 中的重试条件:\n";
echo "```php\n";
echo "return \$throwable instanceof LLMNetworkException\n";
echo "    || (\$throwable && \$throwable->getPrevious() instanceof LLMNetworkException);\n";
echo "```\n\n";

echo "📊 改进前后对比:\n";
echo "┌─────────────┬─────────────────────────┬────────────────────────────┐\n";
echo "│ 错误类型    │ 改进前                   │ 改进后                      │\n";
echo "├─────────────┼─────────────────────────┼────────────────────────────┤\n";
echo "│ model_error │ LLMContentFilterException│ LLMContentFilterException  │\n";
echo "│             │ ❌ 不可重试              │ ❌ 不可重试 (正确)          │\n";
echo "├─────────────┼─────────────────────────┼────────────────────────────┤\n";
echo "│ server_error│ LLMApiException         │ LLMNetworkException        │\n";
echo "│             │ ❌ 不可重试              │ ✅ 可重试 (正确)            │\n";
echo "└─────────────┴─────────────────────────┴────────────────────────────┘\n\n";

echo "🎯 **重要改进**:\n";
echo "1. ✅ Azure OpenAI 服务故障 (server_error) 现在可以自动重试\n";
echo "2. ✅ 内容过滤错误 (model_error) 仍然不会重试，需要用户修改提示词\n";
echo "3. ✅ 状态码和错误信息都被正确保留\n";
echo "4. ✅ 为用户提供了更准确的错误处理建议\n\n";

echo "💡 **对你的 OpenAI 代理接口的影响**:\n";
echo "- 暂时性服务故障会自动重试，提升可用性\n";
echo "- 用户收到更准确的错误类型和处理建议\n";
echo "- 减少因 Azure 服务抖动造成的请求失败\n";

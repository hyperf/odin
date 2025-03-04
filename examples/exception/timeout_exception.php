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
 * 异常捕获与处理示例 - 请求超时错误.
 *
 * 本示例展示了如何处理因模型响应超时而触发的异常。
 * 该示例通过两种方式触发超时：
 * 1. 设置非常短的总超时时间（仅3秒）
 * 2. 发送一个需要大量计算和思考的复杂请求
 *
 * 这确保了请求会因超时而失败，然后错误处理器捕获并处理这个异常。
 */
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 2));
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ModelOptions;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
// 创建日志记录器
$logger = new Logger();

// 初始化错误处理器
$errorHandler = new LLMErrorHandler(
    logger: $logger,
    customMappingRules: [], // 可以添加自定义错误映射规则
    logErrors: true // 是否记录错误日志
);

try {
    $model = ModelFactory::create(
        implementation: AzureOpenAIModel::class,
        modelName: 'gpt-4o-global',
        config: [
            'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
            'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
            'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
            'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
        ],
        modelOptions: ModelOptions::fromArray([
            'chat' => true,
            'function_call' => true,
        ]),
        apiOptions: ApiOptions::fromArray([
            'timeout' => [
                'connection' => 5.0,
                'total' => 3.0, // 设置非常短的超时时间以触发超时错误
            ],
        ]),
        logger: $logger
    );

    // 发送需要复杂计算的请求，可能导致模型思考时间过长
    $messages = [
        new UserMessage('请详细计算并解释霍金辐射的完整数学推导过程，包括所有中间步骤和公式。同时分析黑洞信息悖论，并提供至少5种可能的解决方案，每种解决方案都要包含详细的数学证明。'),
    ];
    $response = $model->chat($messages);
} catch (LLMException $llmException) {
    echo "请求超时错误:\n";
    echo '异常类型: ' . get_class($llmException) . "\n";
    echo '错误消息: ' . $llmException->getMessage() . "\n";
    echo '错误代码: ' . $llmException->getErrorCode() . "\n";

    // 生成并打印错误报告
    $errorReport = $errorHandler->generateErrorReport($llmException);
    echo '错误报告: ' . json_encode($errorReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

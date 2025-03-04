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
 * 异常捕获与处理示例 - 多模态不支持错误.
 *
 * 本示例展示了如何处理多模态请求中因错误图片URL导致的异常。
 * 该示例尝试发送包含不存在图片URL的请求，这会导致模型抛出异常，
 * 我们通过异常处理器捕获并生成错误报告。
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
use Hyperf\Odin\Message\UserMessageContent;
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
            'multi_modal' => true,
        ]),
        apiOptions: ApiOptions::fromArray([
            'timeout' => [
                'connection' => 10.0,
                'total' => 30.0,
            ],
        ]),
        logger: $logger
    );

    $userMessage = new UserMessage();
    $userMessage->addContent(UserMessageContent::text('请描述这张图片'));
    $userMessage->addContent(UserMessageContent::imageUrl('https://non-existent-image-url.jpg'));

    $messages = [
        $userMessage,
    ];
    $response = $model->chat($messages);
} catch (LLMException $llmException) {
    echo "多模态不支持错误(错误的图片地址):\n";
    echo '异常类型: ' . get_class($llmException) . "\n";
    echo '错误消息: ' . $llmException->getMessage() . "\n";
    echo '错误代码: ' . $llmException->getErrorCode() . "\n";

    // 生成并打印错误报告
    $errorReport = $errorHandler->generateErrorReport($llmException);
    echo '错误报告: ' . json_encode($errorReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

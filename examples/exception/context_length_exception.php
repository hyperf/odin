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
 * 异常捕获与处理示例 - 上下文长度超出限制.
 *
 * 本示例展示了如何处理因提交超大文本导致的上下文长度超出异常。
 * 该示例通过生成超大文本（重复多次的文本片段）来触发模型的上下文长度限制。
 * 当提交的文本超过模型的处理能力（128K token）时，会引发相应异常，
 * 然后由错误处理器捕获处理。
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
use Hyperf\Odin\Model\DoubaoModel;
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
        implementation: DoubaoModel::class,
        modelName: env('DOUBAO_PRO_32K_ENDPOINT'),
        config: [
            'api_key' => env('DOUBAO_API_KEY'),
            'base_url' => env('DOUBAO_BASE_URL'),
        ],
        modelOptions: ModelOptions::fromArray([
            'chat' => true,
            'function_call' => true,
        ]),
        apiOptions: ApiOptions::fromArray([
            'timeout' => [
                'connection' => 10.0,
                'total' => 30.0,
            ],
        ]),
        logger: $logger
    );

    // 生成超大文本（超过模型上下文窗口）
    $largeText = str_repeat('这是一段非常长的文本，用于测试上下文长度限制。这段文本会被重复多次直到超出模型的上下文窗口大小。', 5000);
    // 打印当前文本多少 k
    echo '文本长度: ' . strlen($largeText) / 1024 . " KB\n";

    $messages = [
        new UserMessage("请总结以下内容：\n" . $largeText),
    ];
    $response = $model->chat($messages);
} catch (LLMException $llmException) {
    echo "上下文长度超出限制:\n";
    echo '异常类型: ' . get_class($llmException) . "\n";
    echo '错误消息: ' . $llmException->getMessage() . "\n";
    echo '错误代码: ' . $llmException->getErrorCode() . "\n";

    // 生成并打印错误报告
    $errorReport = $errorHandler->generateErrorReport($llmException);
    echo '错误报告: ' . json_encode($errorReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

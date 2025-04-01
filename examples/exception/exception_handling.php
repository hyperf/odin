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
 * 异常捕获与处理示例 - 真实场景.
 *
 * 本示例展示了如何使用 Hyperf\Odin 组件中的异常处理机制来捕获和处理各种真实场景下的异常:
 *
 * 1. 配置/域名异常: 使用不存在的域名 (api.azure-wrong-domain.com) 触发无法解析主机名的错误
 * 2. 网络连接异常: 使用不存在的域名 (this-domain-does-not-exist-12345.com) 触发网络连接错误
 * 3. 连接超时异常: 使用不可路由的IP地址 (10.255.255.1) 和短超时时间触发连接超时异常
 * 4. 自定义错误映射规则: 展示如何添加自定义错误映射规则来处理特定类型的异常
 *
 * 每个示例都通过真实场景触发异常，然后使用错误处理器来处理该异常，并生成错误报告。
 * 这种方法比手动创建异常对象更接近实际应用中的情况，可以更好地测试异常处理机制。
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
    // 故意使用错误的API密钥配置
    $model = ModelFactory::create(
        implementation: AzureOpenAIModel::class,
        modelName: 'gpt-4o-global',
        config: [
            'api_key' => 'invalid_api_key_123456', // 无效的API密钥
            'api_base' => 'https://api.azure-wrong-domain.com/openai', // 错误的API基础URL
            'api_version' => '2023-05-15',
            'deployment_name' => 'gpt-4o-global',
        ],
        modelOptions: ModelOptions::fromArray([
            'chat' => true,
            'function_call' => true,
        ]),
        apiOptions: ApiOptions::fromArray([
            'timeout' => [
                'connection' => 2.0, // 减少连接超时时间以便快速获取错误
                'total' => 5.0,
            ],
        ]),
        logger: $logger
    );

    // 尝试使用模型进行对话，这里应该会失败
    $messages = [
        new UserMessage('测试消息'),
    ];
    $response = $model->chat($messages);
} catch (LLMException $llmException) {
    echo "示例1 - 配置异常(无法解析LLM服务域名):\n";
    echo '异常类型: ' . get_class($llmException) . "\n";
    echo '错误消息: ' . $llmException->getMessage() . "\n";
    echo '错误代码: ' . $llmException->getErrorCode() . "\n";

    // 生成并打印错误报告
    $errorReport = $errorHandler->generateErrorReport($llmException);
    echo '错误报告: ' . json_encode($errorReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

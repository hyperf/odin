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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 2));

require_once dirname(__FILE__, 3) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AwsBedrockModel;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建 AWS Bedrock 模型实例
// 使用 Claude 3 Sonnet 模型 ID
$model = new AwsBedrockModel(
    'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    new Logger(),
);

$model->setApiRequestOptions(new ApiOptions([
    // 如果你的环境不需要代理，那就不用
    'proxy' => env('HTTP_CLIENT_PROXY'),
    // HTTP 处理器配置 - 支持环境变量 ODIN_HTTP_HANDLER
    'http_handler' => env('ODIN_HTTP_HANDLER', 'auto'),
]));

echo '=== AWS Bedrock Claude 流式响应测试 ===' . PHP_EOL;
echo PHP_EOL;

$messages = [
    new SystemMessage('你是一位友好、专业的AI助手。每次回答问题必须携带 emoji 表情。'),
    new UserMessage('请解释量子纠缠的原理，并举一个实际应用的例子'),
];

$start = microtime(true);

// 使用流式API调用，正确传递参数：messages, temperature, maxTokens, stop, tools
$streamResponse = $model->chatStream($messages, 0.7, 4096, []);

echo '开始接收流式响应...' . PHP_EOL;

/** @var ChatCompletionChoice $choice */
foreach ($streamResponse->getStreamIterator() as $choice) {
    $message = $choice->getMessage();
    if ($message instanceof AssistantMessage) {
        echo $message->getReasoningContent() ?? $message->getContent();
    }
}

echo PHP_EOL . '耗时: ' . round(microtime(true) - $start, 2) . ' 秒' . PHP_EOL;

// Display usage information
$usage = $streamResponse->getUsage();
if ($usage) {
    echo PHP_EOL . '=== Token 使用情况 ===' . PHP_EOL;
    echo '输入 Tokens: ' . $usage->getPromptTokens() . PHP_EOL;
    echo '输出 Tokens: ' . $usage->getCompletionTokens() . PHP_EOL;
    echo '总计 Tokens: ' . $usage->getTotalTokens() . PHP_EOL;
}

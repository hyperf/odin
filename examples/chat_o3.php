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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$model = new AzureOpenAIModel(
    'o4-mini-global',
    [
        'api_key' => env('AZURE_OPENAI_O3_API_KEY'),
        'api_base' => env('AZURE_OPENAI_O3_API_BASE'),
        'api_version' => env('AZURE_OPENAI_O3_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_O3_DEPLOYMENT_NAME'),
    ],
    new Logger(),
);

$messages = [
    new SystemMessage(''),
    new UserMessage('你是谁'),
];

$start = microtime(true);

// 使用非流式API调用
$response = $model->chat($messages, temperature: 1, maxTokens: 2048);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getReasoningContent() ?? $message->getContent();
}

echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

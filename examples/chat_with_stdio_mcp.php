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
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$mcpServerManager = new McpServerManager([
    new McpServerConfig(
        type: McpType::Stdio,
        name: 'stdio 工具',
        command: 'php',
        args: [
            BASE_PATH . '/examples/mcp/stdio_server.php',
        ]
    ),
]);

$model = new AzureOpenAIModel(
    'gpt-4o-global',
    [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    new Logger(),
);
$model->getModelOptions()->setFunctionCall(true);
$model->registerMcpServerManager($mcpServerManager);

$messages = [
    new SystemMessage(''),
    new UserMessage('echo 一个字符串：odin'),
];

$start = microtime(true);

// 使用非流式API调用
$request = new ChatCompletionRequest($messages);
$response = $model->chatWithRequest($request);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
var_dump($message);

echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

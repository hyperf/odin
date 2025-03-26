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
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型
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
        'embedding' => false,
        'multi_modal' => true,
        'vector_size' => 0,
    ]),
    apiOptions: ApiOptions::fromArray([
        'timeout' => [
            'connection' => 5.0,  // 连接超时（秒）
            'write' => 10.0,      // 写入超时（秒）
            'read' => 300.0,      // 读取超时（秒）
            'total' => 350.0,     // 总体超时（秒）
            'thinking' => 120.0,  // 思考超时（秒）
            'stream_chunk' => 30.0, // 流式块间超时（秒）
            'stream_first' => 60.0, // 首个流式块超时（秒）
        ],
        'custom_error_mapping_rules' => [],
    ]),
    logger: $logger
);

// 初始化内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个能够使用工具的AI助手，当需要使用工具时，请明确指出工具的作用和使用步骤。'));

// 添加一个无参数的工具示例
$currentTimeTool = new class extends AbstractTool {
    protected function handle(array $parameters): array
    {
        // 这个工具不需要任何参数，直接返回当前时间信息
        return [
            'current_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'timestamp' => time(),
        ];
    }
};
$currentTimeTool->setName('get_current_time');
$currentTimeTool->setDescription('获取当前系统时间，不需要任何参数');
$currentTimeTool->setParameters(ToolParameters::fromArray([
    'type' => 'object',
    'properties' => [],
    'required' => [],
]));

$currentTimeTool->run([]);

// 创建带有所有工具的代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $currentTimeTool->getName() => $currentTimeTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 顺序流式调用示例
echo "===== 顺序流式工具调用示例 =====\n";
$start = microtime(true);

$userMessage = new UserMessage('获取当前系统时间。请详细说明每一步。');
$response = $agent->chatStreamed($userMessage);

$content = '';
/** @var ChatCompletionChoice $choice */
foreach ($response as $choice) {
    $delta = $choice->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        $content .= $delta;
    }
}

echo "\n";
echo '顺序流式调用耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;

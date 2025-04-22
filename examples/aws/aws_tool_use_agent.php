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
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型
$model = ModelFactory::create(
    implementation: AwsBedrockModel::class,
    modelName: 'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    config: [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
        'auto_cache' => true,
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
        'proxy' => env('HTTP_CLIENT_PROXY'),
        'custom_error_mapping_rules' => [],
    ]),
    logger: $logger
);

// 初始化内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个能够使用工具的AI助手，当需要使用工具时，请明确指出工具的作用和使用步骤。'));

// 定义多个工具
// 计算器工具
$calculatorTool = new ToolDefinition(
    name: 'calculator',
    description: '用于执行基本数学运算的计算器工具',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'operation' => [
                'type' => 'string',
                'enum' => ['add', 'subtract', 'multiply', 'divide'],
                'description' => '要执行的数学运算类型',
            ],
            'a' => [
                'type' => 'number',
                'description' => '第一个操作数',
            ],
            'b' => [
                'type' => 'number',
                'description' => '第二个操作数',
            ],
        ],
        'required' => ['operation', 'a', 'b'],
    ]),
    toolHandler: function ($params) {
        $a = $params['a'];
        $b = $params['b'];
        switch ($params['operation']) {
            case 'add':
                return ['result' => $a + $b];
            case 'subtract':
                return ['result' => $a - $b];
            case 'multiply':
                return ['result' => $a * $b];
            case 'divide':
                if ($b == 0) {
                    return ['error' => '除数不能为零'];
                }
                return ['result' => $a / $b];
            default:
                return ['error' => '未知操作'];
        }
    }
);

// 天气查询工具 (模拟)
$weatherTool = new ToolDefinition(
    name: 'weather',
    description: '查询指定城市的天气信息',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => '要查询天气的城市名称',
            ],
        ],
        'required' => ['city'],
    ]),
    toolHandler: function ($params) {
        $city = $params['city'];
        // 模拟天气数据
        $weatherData = [
            '北京' => ['temperature' => '25°C', 'condition' => '晴朗', 'humidity' => '45%'],
            '上海' => ['temperature' => '28°C', 'condition' => '多云', 'humidity' => '60%'],
            '广州' => ['temperature' => '30°C', 'condition' => '阵雨', 'humidity' => '75%'],
            '深圳' => ['temperature' => '29°C', 'condition' => '晴朗', 'humidity' => '65%'],
        ];

        if (isset($weatherData[$city])) {
            return $weatherData[$city];
        }
        return ['error' => '没有找到该城市的天气信息'];
    }
);

// 翻译工具 (模拟)
$translateTool = new ToolDefinition(
    name: 'translate',
    description: '将文本从一种语言翻译成另一种语言',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'text' => [
                'type' => 'string',
                'description' => '要翻译的文本',
            ],
            'target_language' => [
                'type' => 'string',
                'description' => '目标语言，例如：英语、中文、日语等',
            ],
        ],
        'required' => ['text', 'target_language'],
    ]),
    toolHandler: function ($params) {
        $text = $params['text'];
        $targetLanguage = $params['target_language'];

        // 模拟翻译结果
        $translations = [
            '你好' => [
                '英语' => 'Hello',
                '日语' => 'こんにちは',
                '法语' => 'Bonjour',
            ],
            'Hello' => [
                '中文' => '你好',
                '日语' => 'こんにちは',
                '法语' => 'Bonjour',
            ],
        ];

        if (isset($translations[$text][$targetLanguage])) {
            return ['translated_text' => $translations[$text][$targetLanguage]];
        }

        // 如果没有预设的翻译，返回原文加上模拟的后缀
        return ['translated_text' => $text . ' (已翻译为' . $targetLanguage . ')', 'note' => '这是模拟翻译'];
    }
);

// 创建带有所有工具的代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $calculatorTool->getName() => $calculatorTool,
        $weatherTool->getName() => $weatherTool,
        $translateTool->getName() => $translateTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 顺序调用示例
echo "===== 顺序工具调用示例 =====\n";
$start = microtime(true);

$userMessage = new UserMessage('请计算 23 × 45，然后查询北京的天气，最后将"你好"翻译成英语。请详细说明每一步。');
$response = $agent->chat($userMessage);

$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getContent();
}

echo "\n";
echo '顺序调用耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;

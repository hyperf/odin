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
use Hyperf\Odin\Model\DashScopeModel;
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
    implementation: DashScopeModel::class,
    modelName: env('QWEN3_CODER_PLUS_MODEL'),
    config: [
        'api_key' => env('QWEN_API_KEY'),
        'base_url' => env('QWEN_API_BASE_URL'),
        'auto_cache_config' => [
            'auto_enabled' => true,  // 启用自动缓存
            'min_cache_tokens' => 1024,
            'supported_models' => ['qwen3-coder-plus', 'qwen-max'],
        ],
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
$systemPrompt = '你是一个专业且智能的AI助手，具备丰富的知识库和强大的工具使用能力。你的主要职责是帮助用户解决各种问题，并在需要时合理使用可用的工具来提供准确、及时的信息和服务。

## 工具使用原则

### 1. 工具选择策略
- 当用户的需求需要实时数据、精确计算或特定功能时，优先考虑使用相应的工具
- 在使用工具前，先分析用户需求，选择最合适的工具组合
- 对于复杂任务，可以按逻辑顺序使用多个工具
- 如果某个工具无法满足需求，主动说明原因并提供替代方案

### 2. 工具调用规范
- 使用工具前，向用户清楚说明将要使用的工具及其作用
- 调用工具时确保参数正确完整，避免错误调用
- 工具返回结果后，对结果进行解读和总结
- 如果工具返回错误，要向用户说明错误原因并提供解决建议

### 3. 响应格式要求
- 回复结构清晰，逻辑层次分明
- 使用工具时采用以下格式：
  1. 说明即将使用的工具和原因
  2. 调用工具并展示结果
  3. 对结果进行分析和解释
  4. 根据结果给出最终答案或建议

## 可用工具说明

### 计算器工具 (calculator)
功能：执行基本数学运算（加、减、乘、除）
使用场景：需要进行精确数学计算时
参数要求：
- operation: 运算类型（add/subtract/multiply/divide）
- a: 第一个操作数
- b: 第二个操作数

### 天气查询工具 (weather)
功能：查询指定城市的天气信息
使用场景：用户询问天气情况时
参数要求：
- city: 城市名称
注意：当前支持北京、上海、广州、深圳等主要城市

### 翻译工具 (translate)
功能：将文本从一种语言翻译成另一种语言
使用场景：用户需要翻译服务时
参数要求：
- text: 要翻译的文本内容
- target_language: 目标语言

## 交互指导原则

### 1. 用户体验优先
- 始终保持友好、专业的对话态度
- 主动了解用户需求，提供个性化服务
- 回复要简洁明了，避免冗余信息
- 对于复杂问题，提供分步解决方案

### 2. 准确性保证
- 使用工具获得的数据要如实呈现
- 对于无法确定的信息，明确说明不确定性
- 区分事实信息和推测内容
- 承认知识局限性，必要时建议用户咨询专业人士

### 3. 安全和隐私
- 保护用户隐私，不泄露敏感信息
- 对于涉及安全的操作，提供必要的警告和建议
- 拒绝执行可能造成危害的请求
- 遵守相关法律法规和道德规范

### 4. 持续学习
- 从用户反馈中改进服务质量
- 灵活应对各种场景和需求
- 保持开放心态，接受新的挑战
- 不断优化工具使用效率

## 特殊情况处理

### 工具故障处理
- 如果工具调用失败，立即向用户说明情况
- 提供人工替代方案或建议重试
- 记录问题详情，便于后续改进

### 多工具协作
- 合理规划工具使用顺序
- 确保前一个工具的输出能为下一个工具提供有效输入
- 对整个工具链的执行过程进行监控和优化

### 异常情况应对
- 面对超出工具能力范围的需求，诚实说明限制
- 提供可行的替代解决方案
- 引导用户调整需求或寻求其他帮助渠道

通过以上原则和规范，我将为你提供高质量、可靠的智能助手服务。请随时告诉我你的需求，我会选择最合适的方式来帮助你。';

$memory->addSystemMessage(new SystemMessage($systemPrompt));

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

# 示例项目

> 本章节展示了使用 Odin 框架构建的各类实际应用示例，帮助您快速了解如何将框架应用到不同场景中。

## 基础聊天应用

聊天应用是最基础也是最常见的 LLM 应用场景。以下示例展示了如何使用 Odin 构建一个简单的聊天机器人：

```php
<?php

declare(strict_types=1);

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

// 初始化模型
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

// 准备消息
$messages = [
    new SystemMessage(''),
    new UserMessage('请解释量子纠缠的原理，并举一个实际应用的例子'),
];

$start = microtime(true);

// 使用非流式API调用
$response = $model->chat($messages);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getReasoningContent() ?? $message->getContent();
}

echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;
```

## 流式聊天应用

对于需要实时输出的场景，Odin 提供了流式处理能力，可以更快地向用户展示响应，提升用户体验：

```php
<?php

declare(strict_types=1);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\DoubaoModel;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 初始化模型（此示例使用的是DoubaoModel，可替换为其他支持流式输出的模型）
$model = new DoubaoModel(
    env('DEEPSPEEK_R1_ENDPOINT'),
    [
        'api_key' => env('DOUBAO_API_KEY'),
        'base_url' => env('DOUBAO_BASE_URL'),
    ],
    new Logger(),
);

// 准备消息
$messages = [
    new SystemMessage(''),
    new UserMessage('请解释量子纠缠的原理，并举一个实际应用的例子'),
];

// 使用流式API调用
$response = $model->chatStream($messages);

$start = microtime(true);

// 迭代流式响应
/** @var ChatCompletionChoice $choice */
foreach ($response->getStreamIterator() as $choice) {
    $message = $choice->getMessage();
    if ($message instanceof AssistantMessage) {
        echo $message->getReasoningContent() ?? $message->getContent();
    }
}
echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;
```

## 工具使用代理（Agent）

Odin 框架支持工具调用，可以让 LLM 通过调用工具来完成更复杂的任务。以下示例展示了如何创建一个具备工具调用能力的 Agent：

```php
<?php

declare(strict_types=1);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

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
use Hyperf\Odin\Model\AzureOpenAIModel;
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

// 定义计算器工具
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

// 定义天气查询工具（模拟）
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

// 定义翻译工具（模拟）
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
```

## 流式工具使用代理

对于需要实时反馈的场景，Odin 也支持流式工具调用，能够在处理复杂任务的同时提供实时输出：

```php
<?php

declare(strict_types=1);

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
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型（这里使用了与非流式示例相同的配置）
// ...

// 定义各种工具（可以与非流式示例相同的工具定义）
// ...

// 创建Agent代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        'calculator' => $calculatorTool,
        'database' => $databaseTool,
        'recommend' => $recommendTool
    ],
    temperature: 0.7,
    logger: $logger
);

// 执行流式工具调用
echo "===== 流式工具调用示例 =====\n";
$start = microtime(true);

$userMessage = new UserMessage('请查询产品表中ID为2的商品信息，并基于其特点推荐3部同类型的电影');

$agent->chatStream(
    $userMessage,
    function (string $chunk, bool $isToolCall = false) {
        if ($isToolCall) {
            echo "\n[工具调用] " . $chunk . "\n";
        } else {
            echo $chunk;
            // 确保输出立即显示
            if (function_exists('ob_flush') && function_exists('flush')) {
                ob_flush();
                flush();
            }
        }
    }
);

echo "\n";
echo '流式调用耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;
```

## MCP 集成示例

Odin 框架支持 Model Context Protocol (MCP) 集成，基于 **[dtyq/php-mcp](https://github.com/dtyq/php-mcp)** 库实现，可以轻松接入外部工具和服务。

### HTTP MCP 服务器集成

以下示例展示如何使用 HTTP MCP 服务器（如高德地图API）：

```php
<?php

declare(strict_types=1);

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

// 配置 MCP 服务器管理器
$mcpServerManager = new McpServerManager([
    new McpServerConfig(
        McpType::Http,
        '高德地图',
        'https://mcp.amap.com/sse?key=' . env('AMAP_API_KEY'),
    ),
]);

// 初始化模型
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

// 启用函数调用并注册 MCP 服务器
$model->getModelOptions()->setFunctionCall(true);
$model->registerMcpServerManager($mcpServerManager);

// 准备消息
$messages = [
    new SystemMessage('你是一个智能助手，可以使用地图API来查询位置和天气信息。'),
    new UserMessage('使用高德地图API查询深圳20250101的天气情况'),
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
```

### STDIO MCP 服务器集成

以下示例展示如何使用本地 STDIO MCP 服务器：

```php
<?php

declare(strict_types=1);

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

// 配置本地 STDIO MCP 服务器
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

// 初始化模型
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

// 启用函数调用并注册 MCP 服务器
$model->getModelOptions()->setFunctionCall(true);
$model->registerMcpServerManager($mcpServerManager);

// 准备消息
$messages = [
    new SystemMessage('你是一个智能助手，可以使用各种本地工具。'),
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
```

### MCP 与 Agent 集成示例

结合 ToolUseAgent 使用 MCP 工具，实现更复杂的任务执行：

```php
<?php

declare(strict_types=1);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
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
            'connection' => 5.0,
            'write' => 10.0,
            'read' => 300.0,
            'total' => 350.0,
            'thinking' => 120.0,
            'stream_chunk' => 30.0,
            'stream_first' => 60.0,
        ],
        'custom_error_mapping_rules' => [],
    ]),
    logger: $logger
);

// 配置多个 MCP 服务器
$key = $_ENV['MCP_API_KEY'] ?? getenv('MCP_API_KEY') ?: '123456';
$mcpServerManager = new McpServerManager([
    // HTTP MCP 服务器（高德地图）
    new McpServerConfig(
        McpType::Http,
        '高德地图',
        'https://mcp.amap.com/sse?key=' . $key,
    ),
    // STDIO MCP 服务器（本地工具）
    new McpServerConfig(
        type: McpType::Stdio,
        name: '本地工具',
        command: 'php',
        args: [
            BASE_PATH . '/examples/mcp/stdio_server.php',
        ]
    ),
]);

// 注册 MCP 服务器到模型
$model->registerMcpServerManager($mcpServerManager);

// 初始化内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个智能助手，能够使用各种工具完成复杂任务，包括地图查询、天气预报和本地计算等。'));

// 定义本地计算工具
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

// 创建带有工具的代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $calculatorTool->getName() => $calculatorTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 执行复杂任务
echo "===== MCP 集成工具调用示例 =====\n";
$start = microtime(true);

$userMessage = new UserMessage('请计算 23 × 45，然后查询北京明天的天气，最后 echo 一个字符串：hello-odin。请详细说明每一步。');
$response = $agent->chat($userMessage);

$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getContent();
}

echo "\n";
echo 'MCP 集成调用耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;
```

在这个示例中，Agent 会依次：
1. 使用本地计算器工具计算 23 × 45
2. 使用高德地图 MCP 服务查询北京天气
3. 使用本地 STDIO MCP 服务执行 echo 命令
4. 整合所有结果生成完整的回答

### MCP 工具发现示例

```php
<?php

// 获取所有可用的 MCP 工具信息
$tools = $mcpServerManager->getAllTools();

echo "===== 可用的 MCP 工具 =====\n";
foreach ($tools as $tool) {
    echo "工具名称: " . $tool->getName() . "\n";
    echo "工具描述: " . $tool->getDescription() . "\n";
    echo "参数定义: " . json_encode($tool->getParameters()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "---\n";
}

// 直接调用特定的 MCP 工具
try {
    $result = $mcpServerManager->callMcpTool('mcp_a_weather', [
        'city' => '北京'
    ]);
    
    echo "直接调用结果:\n";
    var_dump($result);
} catch (Exception $e) {
    echo "工具调用失败: " . $e->getMessage() . "\n";
}
```

这些示例展示了 Odin 框架如何通过 `dtyq/php-mcp` 库无缝集成 MCP 协议，为您的 AI 应用提供强大的外部工具能力扩展。

## 进阶应用场景

Odin 框架可以应用于各种复杂场景，例如：

### 1. 多模态应用

处理图像和文本的多模态应用：

```php
<?php

declare(strict_types=1);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 2));

require_once dirname(__FILE__, 3) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Model\ModelOptions;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建支持多模态的模型实例
$model = new AwsBedrockModel(
    'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    new Logger(),
);
$model->setModelOptions(new ModelOptions([
    'multi_modal' => true,
]));
$model->setApiRequestOptions(new ApiOptions([
    'proxy' => env('HTTP_CLIENT_PROXY'),
]));

// 使用本地文件测试并转换为 base64
$imagePath = __DIR__ . '/vision_test.jpeg';
$imageData = file_get_contents($imagePath);
$base64Image = base64_encode($imageData);
$imageType = mime_content_type($imagePath);
$dataUrl = "data:{$imageType};base64,{$base64Image}";

// 创建包含图像的消息
$userMessage = new UserMessage();
$userMessage->addContent(UserMessageContent::text('分析一下这张图片里有什么内容？什么颜色最多？'));
$userMessage->addContent(UserMessageContent::imageUrl($dataUrl));

$multiModalMessages = [
    new SystemMessage('你是一位专业的图像分析专家，请详细描述图像内容。'),
    $userMessage,
];

$start = microtime(true);

// 使用非流式API调用
$response = $model->chat($multiModalMessages);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getContent();
}

echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;
```

### 2. 异常处理

处理各种 LLM 调用异常的示例：

```php
<?php

declare(strict_types=1);

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
);

try {
    // 故意使用错误的配置来触发异常
    $model = new AzureOpenAIModel(
        'gpt-4o-global',
        [
            'api_key' => 'invalid-key',
            'api_base' => 'https://api.azure-wrong-domain.com',
            'api_version' => '2023-05-15',
            'deployment_name' => 'wrong-deployment',
        ],
        $logger
    );

    // 尝试进行API调用
    $response = $model->chat([
        new UserMessage('这个请求会失败'),
    ]);
} catch (LLMException $e) {
    // 使用错误处理器处理并输出用户友好的错误信息
    $errorReport = $errorHandler->handle($e);
    
    echo "发生错误：" . $errorReport->getUserMessage() . PHP_EOL;
    echo "错误类型：" . $errorReport->getErrorType() . PHP_EOL;
    echo "错误代码：" . $errorReport->getErrorCode() . PHP_EOL;
    echo "建议解决方法：" . $errorReport->getSuggestion() . PHP_EOL;
    
    // 可以根据错误类型采取不同的恢复策略
    if ($errorReport->isRecoverable()) {
        echo "此错误可恢复，可以尝试重试请求" . PHP_EOL;
    } else {
        echo "此错误不可恢复，需要修复配置后再尝试" . PHP_EOL;
    }
}
```

## 更多示例

Odin 框架的应用场景远不止于此，还可以构建各种高级应用：

1. **RAG（检索增强生成）系统**：结合向量数据库构建知识库问答系统
2. **自动内容生成**：自动撰写文章、摘要、产品描述等
3. **客服自动化**：处理常见问题，路由复杂问题到人工客服
4. **数据分析助手**：分析数据集并生成见解和可视化
5. **工作流自动化**：将 LLM 集成到业务流程中，实现流程自动化

您可以在 [GitHub 仓库](https://github.com/hyperf/odin) 中找到更多示例和详细文档。

---

在下一章中，我们将回答开发过程中的常见问题，帮助您解决在使用 Odin 框架时可能遇到的困难。

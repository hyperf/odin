# MCP 集成

> 本章介绍 Odin 框架中 Model Context Protocol (MCP) 的集成和使用，帮助您轻松接入和管理外部工具服务，扩展 AI 应用的能力边界。

## MCP 概述

### 什么是 MCP？

Model Context Protocol (MCP) 是一个开放的协议标准，用于在 AI 应用与外部服务之间建立安全、标准化的通信机制。通过 MCP，您可以：

- **统一工具接口**：为 AI 模型提供标准化的外部工具访问能力
- **安全通信**：确保 AI 与外部服务间的安全数据交换
- **动态能力扩展**：运行时动态发现和使用新的工具和服务
- **协议兼容性**：支持多种传输方式和认证机制

### Odin 中的 MCP 实现

Odin 框架的 MCP 支持基于 **[dtyq/php-mcp](https://github.com/dtyq/php-mcp)** 库实现。这是一个专业的 PHP MCP 客户端库，提供了完整的 MCP 协议实现，包括：

- **完整的协议支持**：实现 MCP 2024-11-05 版本规范
- **多种传输方式**：支持 STDIO 和 HTTP 传输协议
- **类型安全**：完整的类型定义和参数验证
- **异步支持**：基于 Swoole 的高性能异步处理
- **错误处理**：完善的异常处理和错误恢复机制

Odin 在 `dtyq/php-mcp` 的基础上，提供了更高层次的抽象和集成：

- **无缝集成**：MCP 工具自动映射为 Odin 工具系统
- **统一管理**：通过 McpServerManager 统一管理多个 MCP 服务器
- **Agent 支持**：MCP 工具可直接在 ToolUseAgent 中使用
- **配置简化**：提供更简洁的配置接口和最佳实践

### MCP 在 Odin 中的价值

1. **工具生态扩展**：接入丰富的第三方工具和服务
2. **标准化集成**：统一的工具管理和调用接口
3. **灵活部署**：支持本地和远程服务的混合部署
4. **开箱即用**：内置常用服务的 MCP 连接器

## MCP 架构设计

Odin 的 MCP 集成包含以下核心组件：

### MCP 服务器管理器

- **McpServerManager**：统一管理多个 MCP 服务器的连接和工具注册
- **McpServerConfig**：MCP 服务器的配置管理，支持不同类型的连接方式
- **动态发现**：自动发现和注册 MCP 服务器提供的工具

### 传输层支持

- **STDIO 传输**：基于标准输入输出的本地进程通信
- **HTTP 传输**：基于 HTTP/HTTPS 的远程服务通信
- **可扩展性**：支持自定义传输协议的接入

### 工具映射系统

- **自动注册**：MCP 工具自动映射为 Odin 工具定义
- **命名空间**：防止不同 MCP 服务器间的工具名称冲突
- **参数转换**：自动处理 MCP 工具参数与 Odin 工具参数的转换

## 安装与依赖

### Composer 依赖

Odin 的 MCP 功能依赖于 `dtyq/php-mcp` 库，该库已包含在 Odin 的依赖中。如果您单独使用 MCP 功能，可以通过以下命令安装：

```bash
composer require dtyq/php-mcp
```

### 系统要求

- PHP 8.1 或以上版本
- Swoole 扩展（用于异步 I/O 和协程支持）
- 网络连接（用于 HTTP MCP 服务器）
- 文件执行权限（用于 STDIO MCP 服务器）

## MCP 服务器配置

### STDIO MCP 服务器

STDIO MCP 服务器通过标准输入输出与本地进程通信，适合本地工具和服务的集成：

```php
<?php

use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;

// 创建 STDIO MCP 服务器配置
$mcpServerManager = new McpServerManager([
    new McpServerConfig(
        type: McpType::Stdio,
        name: '本地工具',
        command: 'php',
        args: [
            BASE_PATH . '/mcp/stdio-server.php',
        ]
    ),
]);
```

**配置参数说明：**

- `type`：服务器类型，使用 `McpType::Stdio`
- `name`：服务器名称，用于工具命名空间标识
- `command`：启动命令，通常是解释器或可执行文件
- `args`：命令参数数组，包含脚本路径和其他参数

### HTTP MCP 服务器

HTTP MCP 服务器通过 HTTP/HTTPS 协议与远程服务通信，适合云服务和 API 的集成：

```php
<?php

use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;

// 创建 HTTP MCP 服务器配置
$mcpServerManager = new McpServerManager([
    new McpServerConfig(
        type: McpType::Http,
        name: '高德地图',
        url: 'https://mcp.amap.com/sse?key=YOUR_API_KEY',
        token: 'your-auth-token', // 可选的认证令牌
    ),
]);
```

**配置参数说明：**

- `type`：服务器类型，使用 `McpType::Http`
- `name`：服务器名称，用于工具命名空间标识
- `url`：MCP 服务器的 HTTP 端点 URL
- `token`：可选的 Bearer 认证令牌

### 多服务器配置

Odin 支持同时连接多个 MCP 服务器，实现工具能力的组合：

```php
<?php

$mcpServerManager = new McpServerManager([
    // 本地工具服务器
    new McpServerConfig(
        type: McpType::Stdio,
        name: '计算工具',
        command: 'python',
        args: ['/path/to/calculator-server.py']
    ),
    
    // 远程地图服务
    new McpServerConfig(
        type: McpType::Http,
        name: '地图服务',
        url: 'https://maps-api.example.com/mcp',
        token: getenv('MAPS_API_TOKEN')
    ),
    
    // 远程天气服务
    new McpServerConfig(
        type: McpType::Http,
        name: '天气服务',
        url: 'https://weather-api.example.com/mcp',
        token: getenv('WEATHER_API_TOKEN')
    ),
]);
```

## 模型 MCP 集成

将 MCP 服务器管理器注册到模型中，使模型能够访问 MCP 工具：

```php
<?php

use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Logger;

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

// 启用函数调用功能
$model->getModelOptions()->setFunctionCall(true);

// 注册 MCP 服务器管理器
$model->registerMcpServerManager($mcpServerManager);
```

## 基础使用示例

### 简单聊天与 MCP 工具调用

```php
<?php

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

// 准备消息
$messages = [
    new SystemMessage('你是一个智能助手，可以使用各种工具来帮助用户。'),
    new UserMessage('请查询北京明天的天气情况'),
];

// 发起聊天请求
$request = new ChatCompletionRequest($messages);
$response = $model->chatWithRequest($request);

// 输出响应
$message = $response->getFirstChoice()->getMessage();
echo $message->getContent();
```

在这个示例中，模型会自动：
1. 解析用户请求中的意图
2. 识别需要调用的天气查询工具
3. 调用 MCP 天气服务获取数据
4. 整合结果并生成自然语言响应

### Agent 与 MCP 工具集成

结合 ToolUseAgent 使用 MCP 工具，实现更复杂的任务执行：

```php
<?php

use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

// 创建内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个智能助手，能够使用各种工具完成复杂任务。'));

// 定义本地计算工具
$calculatorTool = new ToolDefinition(
    name: 'calculator',
    description: '执行基本数学运算',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'operation' => [
                'type' => 'string',
                'enum' => ['add', 'subtract', 'multiply', 'divide'],
                'description' => '运算类型',
            ],
            'a' => ['type' => 'number', 'description' => '第一个操作数'],
            'b' => ['type' => 'number', 'description' => '第二个操作数'],
        ],
        'required' => ['operation', 'a', 'b'],
    ]),
    toolHandler: function ($params) {
        $a = $params['a'];
        $b = $params['b'];
        
        return match ($params['operation']) {
            'add' => ['result' => $a + $b],
            'subtract' => ['result' => $a - $b],
            'multiply' => ['result' => $a * $b],
            'divide' => $b != 0 ? ['result' => $a / $b] : ['error' => '除数不能为零'],
            default => ['error' => '未知运算'],
        };
    }
);

// 创建工具使用代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $calculatorTool->getName() => $calculatorTool,
    ],
    temperature: 0.6,
    logger: new Logger()
);

// 执行复杂任务
$userMessage = new UserMessage('请计算 23 × 45 的结果，然后查询深圳明天的天气');
$response = $agent->chat($userMessage);

echo $response->getFirstChoice()->getMessage()->getContent();
```

在这个示例中，Agent 会：
1. 使用本地计算器工具计算 23 × 45
2. 使用 MCP 天气服务查询深圳天气
3. 整合两个结果生成完整回答

## MCP 工具命名规则

为了避免工具名称冲突，Odin 对 MCP 工具采用特定的命名规则：

### 命名格式

```
mcp_{服务器标识}_{原始工具名}
```

### 示例

假设有两个 MCP 服务器：

```php
// 服务器 A (index: 0)
new McpServerConfig(name: '地图服务', ...)  // -> 标识: a

// 服务器 B (index: 1)  
new McpServerConfig(name: '天气服务', ...)  // -> 标识: b
```

它们的工具会被映射为：

- 地图服务的 `search` 工具 → `mcp_a_search`
- 天气服务的 `forecast` 工具 → `mcp_b_forecast`

### 标识生成规则

服务器标识按注册顺序生成：
- 第一个服务器：`a`
- 第二个服务器：`b`
- 第三个服务器：`c`
- ...以此类推

## 工具发现和管理

### 自动工具发现

```php
<?php

// 获取所有可用的 MCP 工具
$tools = $mcpServerManager->getAllTools();

// 输出工具信息
foreach ($tools as $tool) {
    echo "工具名称: " . $tool->getName() . "\n";
    echo "工具描述: " . $tool->getDescription() . "\n";
    echo "参数定义: " . json_encode($tool->getParameters()->toArray()) . "\n";
    echo "---\n";
}
```

### 直接调用 MCP 工具

```php
<?php

// 直接调用 MCP 工具
try {
    $result = $mcpServerManager->callMcpTool('mcp_a_search', [
        'query' => '北京天安门',
        'type' => 'poi'
    ]);
    
    var_dump($result);
} catch (Exception $e) {
    echo "工具调用失败: " . $e->getMessage();
}
```

## 故障排查

### 常见问题

1. **STDIO 服务器启动失败**
   ```
   错误：Transport is not connected
   解决：检查服务器文件路径和执行权限
   ```

2. **HTTP 服务器连接超时**
   ```
   错误：Connection timeout
   解决：检查网络连接和服务器 URL 有效性
   ```

3. **工具调用失败**
   ```
   错误：Tool not found
   解决：确认工具名称拼写和服务器连接状态
   ```

### 调试技巧

1. **启用详细日志**
   ```php
   // 在模型配置中启用调试模式
   $logger = new Logger();
   $logger->setLevel(Logger::DEBUG);
   ```

2. **检查服务器状态**
   ```php
   // 测试 MCP 服务器连接
   $tools = $mcpServerManager->getAllTools();
   if (empty($tools)) {
       echo "未发现任何工具，请检查服务器配置\n";
   }
   ```

3. **验证工具参数**
   ```php
   // 输出工具的参数要求
   foreach ($mcpServerManager->getAllTools() as $tool) {
       echo $tool->getName() . " 参数要求:\n";
       var_dump($tool->getParameters()->toArray());
   }
   ```

## 最佳实践

### 1. 服务器命名约定

使用描述性的服务器名称，便于理解和维护：

```php
// 推荐
new McpServerConfig(name: '高德地图API', ...)
new McpServerConfig(name: '本地计算工具', ...)

// 不推荐
new McpServerConfig(name: 'server1', ...)
new McpServerConfig(name: 'mcp', ...)
```

### 2. 错误处理

实现完善的错误处理机制：

```php
<?php

try {
    $result = $mcpServerManager->callMcpTool($toolName, $params);
    // 处理结果
} catch (InvalidArgumentException $e) {
    // 处理参数错误
    $logger->error('工具参数错误', ['tool' => $toolName, 'error' => $e->getMessage()]);
} catch (McpException $e) {
    // 处理 MCP 通信错误
    $logger->error('MCP 服务器错误', ['error' => $e->getMessage()]);
} catch (Exception $e) {
    // 处理其他未知错误
    $logger->error('未知错误', ['error' => $e->getMessage()]);
}
```

### 3. 资源管理

正确管理 MCP 连接的生命周期：

```php
<?php

class MyApplication 
{
    private McpServerManager $mcpManager;
    
    public function __construct()
    {
        $this->mcpManager = new McpServerManager([...]);
    }
    
    public function __destruct()
    {
        // MCP 管理器会自动清理连接
        // 如需要，可以手动调用清理方法
    }
}
```

### 4. 配置管理

建议将 MCP 配置参数外部化，便于不同环境的管理：

```php
<?php

// 使用环境变量管理配置
$mcpServerManager = new McpServerManager([
    // HTTP MCP 服务器配置
    new McpServerConfig(
        type: McpType::Http,
        name: '地图服务',
        url: env('MAP_SERVICE_URL', 'https://mcp.amap.com/sse?key=123'),
        token: env('MAP_SERVICE_TOKEN'), // 可选
    ),
    
    // STDIO MCP 服务器配置
    new McpServerConfig(
        type: McpType::Stdio,
        name: '本地工具',
        command: env('MCP_STDIO_COMMAND', 'php'),
        args: [
            env('MCP_STDIO_SCRIPT', BASE_PATH . '/mcp/local-tools.php'),
        ]
    ),
]);

// 在 .env 文件中配置：
// MAP_SERVICE_URL=https://your-map-service.com/mcp
// MAP_SERVICE_TOKEN=your-token
// MCP_STDIO_COMMAND=php
// MCP_STDIO_SCRIPT=/path/to/your/stdio-server.php
```

通过 MCP 集成，Odin 框架能够无缝接入丰富的外部工具生态，为您的 AI 应用提供强大的能力扩展基础。 
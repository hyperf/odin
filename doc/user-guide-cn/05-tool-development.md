# 工具开发

> 本章介绍 Odin 框架中工具（Tool）开发的核心概念、设计原则和实现方法，帮助您扩展模型的能力以满足特定业务需求。

## 工具系统概述

### 什么是工具？

在 Odin 框架中，"工具"是指可以被大型语言模型（LLM）动态调用的功能模块，使模型能够执行超出其本身能力范围的任务。工具机制类似于 OpenAI 的 Function Calling 或 Anthropic 的 Tool Use 功能，但经过了标准化和增强处理。

### 工具的核心价值

1. **扩展模型能力**：让模型能够访问外部数据、执行计算或调用系统功能
2. **执行专业任务**：如数据检索、内容生成、API调用等
3. **系统集成**：将 LLM 与您的业务系统和服务无缝对接
4. **流程自动化**：使 Agent 能够自主执行复杂的操作序列

## 工具系统架构

Odin 的工具系统由以下核心组件构成：

### 工具定义与抽象

- **AbstractTool**：所有工具的抽象基类，提供标准化的接口和公共功能
- **ToolDefinition**：定义工具的名称、描述、参数结构和处理逻辑
- **ToolParameters**：基于 JSON Schema 标准的参数定义系统，支持复杂的参数验证

### 工具执行机制

- **ToolExecutor**：负责工具调用的执行器，支持单个调用和批量调用
- **并行执行支持**：自动利用协程特性并行执行多个工具调用，提高性能

### 工具与 Agent 的集成

- **ToolUseAgent**：专门支持工具调用的 Agent 实现，能够解析模型输出并调用对应工具
- **工具链路追踪**：记录工具调用的详细日志，便于调试和分析

## 自定义工具开发流程

### 1. 创建工具类

所有自定义工具都应继承 `AbstractTool` 抽象类：

```php
<?php

namespace App\Tool;

use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolParameters;

class WeatherTool extends AbstractTool
{
    public function __construct()
    {
        // 设置工具名称和描述
        $this->name = 'get_weather';
        $this->description = '获取指定城市的天气信息';
        
        // 定义工具参数
        $this->parameters = new ToolParameters([
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => '城市名称',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => '预报天数',
                    'minimum' => 1,
                    'maximum' => 7,
                ],
            ],
            'required' => ['city'],
        ]);
    }
    
    /**
     * 实现工具核心逻辑
     */
    protected function handle(array $parameters): array
    {
        $city = $parameters['city'];
        $days = $parameters['days'] ?? 1;
        
        // 实现天气查询逻辑
        $weatherData = $this->fetchWeatherData($city, $days);
        
        // 返回结果
        return [
            'weather' => $weatherData,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * 获取天气数据
     */
    private function fetchWeatherData(string $city, int $days): array
    {
        // 这里应实现实际的天气API调用
        // 示例返回模拟数据
        return [
            'city' => $city,
            'forecast' => [
                [
                    'date' => date('Y-m-d'),
                    'condition' => '晴',
                    'temperature' => '25°C',
                ],
                // ... 更多天气数据
            ],
        ];
    }
}
```

### 2. 定义工具参数

工具参数通过 `ToolParameters` 类定义，完全兼容 JSON Schema 标准：

```php
$parameters = new ToolParameters([
    'type' => 'object',
    'properties' => [
        'query' => [
            'type' => 'string',
            'description' => '搜索关键词',
        ],
        'limit' => [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 50,
            'default' => 10,
            'description' => '结果数量限制',
        ],
        'filters' => [
            'type' => 'object',
            'properties' => [
                'date_from' => [
                    'type' => 'string',
                    'format' => 'date',
                    'description' => '起始日期',
                ],
                'categories' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => '类别列表',
                ],
            ],
            'description' => '筛选条件',
        ],
    ],
    'required' => ['query'],
]);
```

您可以使用 JSON Schema 支持的所有数据类型和验证规则：

- 基本类型：`string`、`integer`、`number`、`boolean`、`array`、`object`
- 字符串格式：`date`、`date-time`、`email`、`uri` 等
- 数值验证：`minimum`、`maximum`、`multipleOf`等
- 字符串验证：`minLength`、`maxLength`、`pattern`等
- 数组验证：`minItems`、`maxItems`、`uniqueItems`等
- 条件验证：`oneOf`、`anyOf`、`allOf`、`not`等

### 3. 参数验证和转换

Odin 工具系统内置了强大的参数验证和自动类型转换功能：

#### 参数验证

当调用工具时，系统会根据参数定义自动验证传入的参数：

```php
// 有效参数
$result = $weatherTool->run([
    'city' => '北京',
    'days' => 3,
]); // 成功执行

// 无效参数
try {
    $weatherTool->run([
        'days' => 10, // 超出最大值7
    ]); // 抛出 ToolParameterValidationException
} catch (ToolParameterValidationException $e) {
    // 处理验证错误
    echo $e->getMessage(); // "工具参数验证失败：路径 '': 缺少必需属性 'city'; 路径 'days': 不应大于 7"
}
```

您可以根据需要禁用参数验证：

```php
$tool->setValidateParameters(false);
```

#### 参数自动转换

系统会自动处理参数类型转换，确保参数类型符合定义：

```php
$result = $weatherTool->run([
    'city' => '上海',
    'days' => '5', // 字符串将被自动转换为整数
]);
```

自动转换支持以下情况：
- 字符串转数字：`'5'` → `5`
- 字符串转布尔值：`'true'`/`'yes'`/`'1'` → `true`，`'false'`/`'no'`/`'0'`/`''` → `false`

您也可以禁用自动转换：

```php
$tool->setConvertParameters(false);
```

### 4. 在 Agent 中注册和使用工具

将自定义工具注册到 Agent 以供使用：

```php
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use App\Tool\WeatherTool;
use App\Tool\SearchTool;

// 创建工具实例
$weatherTool = new WeatherTool();
$searchTool = new SearchTool();

// 创建支持工具使用的 Agent
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        'get_weather' => $weatherTool->toToolDefinition(),
        'search' => $searchTool->toToolDefinition(),
    ],
    logger: $logger,
);

// 使用 Agent
$response = $agent->chat(new UserMessage('我想知道北京最近三天的天气预报'));
```

### 5. 处理工具链调用

有时 LLM 需要通过多个工具组合完成复杂任务，Odin 支持自动处理这种调用链：

```php
// 创建工具链
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        'search_database' => $dbSearchTool->toToolDefinition(),
        'generate_report' => $reportTool->toToolDefinition(),
        'send_email' => $emailTool->toToolDefinition(),
    ],
);

// 支持连续工具调用
$response = $agent->chat(new UserMessage('查找最近的销售数据，生成一份报告，然后发送到团队邮箱'));
```

## 工具执行器与并行处理

Odin 框架的 `ToolExecutor` 类支持高效执行工具调用，包括并行执行多个工具：

```php
use Hyperf\Odin\Agent\Tool\ToolExecutor;

// 创建工具执行器
$executor = new ToolExecutor();

// 添加多个工具调用
$executor->add(function() use ($weatherTool) {
    return $weatherTool->run(['city' => '北京']);
});

$executor->add(function() use ($stockTool) {
    return $stockTool->run(['symbol' => 'AAPL']);
});

// 设置是否并行执行（默认为并行）
$executor->setParallel(true);

// 执行所有工具调用
$results = $executor->run();

// 处理结果
foreach ($results as $index => $result) {
    echo "工具 {$index} 执行结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
```

### 并行执行的优势

- **提高性能**：多个独立工具同时执行，节省总体响应时间
- **资源利用**：充分利用系统资源，提高吞吐量
- **异常隔离**：单个工具失败不影响其他工具执行

## 工具测试指南

### 单元测试

为工具创建全面的单元测试确保其正确性和稳定性：

```php
use PHPUnit\Framework\TestCase;
use Hyperf\Odin\Exception\ToolParameterValidationException;
use App\Tool\WeatherTool;

class WeatherToolTest extends TestCase
{
    private WeatherTool $weatherTool;
    
    protected function setUp(): void
    {
        $this->weatherTool = new WeatherTool();
    }
    
    public function testValidParameters()
    {
        $result = $this->weatherTool->run([
            'city' => '上海',
            'days' => 3,
        ]);
        
        $this->assertArrayHasKey('weather', $result);
        $this->assertArrayHasKey('forecast', $result['weather']);
        $this->assertCount(3, $result['weather']['forecast']);
    }
    
    public function testParameterValidation()
    {
        $this->expectException(ToolParameterValidationException::class);
        $this->weatherTool->run([
            'days' => 8, // 超出最大值7
        ]);
    }
    
    public function testParameterConversion()
    {
        // 验证字符串参数能自动转换为整数
        $result = $this->weatherTool->run([
            'city' => '北京',
            'days' => '2',
        ]);
        
        $this->assertArrayHasKey('weather', $result);
        $this->assertCount(2, $result['weather']['forecast']);
    }
}
```

### 模拟外部依赖

测试涉及外部服务的工具时，使用模拟对象隔离测试环境：

```php
public function testWeatherApiIntegration()
{
    // 创建模拟的天气API服务
    $mockWeatherApi = $this->createMock(WeatherApiInterface::class);
    
    // 设置预期行为
    $mockWeatherApi->expects($this->once())
        ->method('getWeather')
        ->with('北京', 2)
        ->willReturn([
            'city' => '北京',
            'forecast' => [
                ['date' => '2023-07-10', 'condition' => '晴', 'temperature' => '30°C'],
                ['date' => '2023-07-11', 'condition' => '多云', 'temperature' => '28°C'],
            ]
        ]);
    
    // 注入模拟对象
    $weatherTool = new WeatherTool($mockWeatherApi);
    
    // 执行测试
    $result = $weatherTool->run(['city' => '北京', 'days' => 2]);
    
    // 验证结果
    $this->assertEquals('北京', $result['weather']['city']);
    $this->assertCount(2, $result['weather']['forecast']);
}
```

## 工具开发最佳实践

### 设计原则

1. **职责单一**：每个工具应专注于解决单一明确的问题
2. **接口清晰**：提供直观的参数结构和返回值格式
3. **描述明确**：详细描述工具的用途、参数和行为，便于 LLM 正确理解何时使用
4. **错误处理**：妥善处理参数错误和业务异常，返回信息丰富的错误消息
5. **资源释放**：确保工具正确释放资源，特别是在处理文件、网络连接等资源时

### 参数设计指南

1. **类型严格**：明确定义参数类型，避免类型混淆
2. **必要参数**：只将必需参数标记为 required，其他提供合理默认值
3. **验证规则**：使用 JSON Schema 验证规则限制参数范围
4. **描述完善**：为每个参数提供清晰的描述说明

### 返回值设计

1. **结构统一**：使用一致的结构返回数据
2. **信息完整**：包含足够的上下文信息便于理解
3. **状态表示**：明确表示操作是否成功
4. **错误详情**：当操作失败时提供详细的错误原因

### 安全性考虑

1. **输入验证**：严格验证并清理所有输入
2. **权限控制**：确保工具只执行授权范围内的操作
3. **敏感信息**：避免在返回值中包含敏感数据
4. **资源限制**：防止工具消耗过多系统资源

## 常见工具类型

在实际应用中，以下是最常见的几类工具：

### 数据检索工具

从数据库、搜索引擎或其他数据源获取信息：

```php
class DatabaseSearchTool extends AbstractTool
{
    protected function handle(array $parameters): array
    {
        $query = $parameters['query'];
        $limit = $parameters['limit'] ?? 10;
        
        // 执行数据库查询
        $results = $this->databaseService->search($query, $limit);
        
        return ['results' => $results];
    }
}
```

### API 集成工具

调用外部 API 获取数据或执行操作：

```php
class ExternalApiTool extends AbstractTool
{
    protected function handle(array $parameters): array
    {
        // 调用外部 API
        $response = $this->httpClient->request(
            'GET',
            'https://api.example.com/data',
            ['query' => $parameters]
        );
        
        return ['data' => json_decode($response->getBody(), true)];
    }
}
```

### 文件处理工具

读取、写入或处理文件：

```php
class FileProcessingTool extends AbstractTool
{
    protected function handle(array $parameters): array
    {
        $filePath = $parameters['file_path'];
        $operation = $parameters['operation'];
        
        if ($operation === 'read') {
            $content = file_get_contents($filePath);
            return ['content' => $content];
        }
        
        if ($operation === 'write') {
            file_put_contents($filePath, $parameters['content']);
            return ['success' => true];
        }
        
        return ['error' => '不支持的操作'];
    }
}
```

### 内容生成工具

生成特定格式的内容：

```php
class ContentGeneratorTool extends AbstractTool
{
    protected function handle(array $parameters): array
    {
        $template = $parameters['template'];
        $data = $parameters['data'];
        
        // 使用模板引擎生成内容
        $content = $this->templateEngine->render($template, $data);
        
        return ['content' => $content];
    }
}
```

## 结论

工具系统是 Odin 框架的核心功能之一，通过开发自定义工具，您可以大幅扩展 LLM 的能力边界，使其能够执行各种实际业务任务。遵循本章提供的指南和最佳实践，您可以开发出高效、安全且易于维护的工具，充分发挥 AI 在您的应用中的潜力。 
# Agent 开发

> 本章详细介绍 Odin 框架中的 Agent 系统，包括其架构设计、工具调用机制和实际应用场景，帮助您构建能够使用工具执行复杂任务的智能应用。

## Agent 架构

在 Odin 框架中，Agent 是一个自主决策的交互系统，它整合了大语言模型（LLM）、工具调用、记忆管理和决策逻辑，形成一个能够完成复杂任务的智能体。Agent 架构主要由以下几个核心组件构成：

### 核心组件

- **模型（Model）**：Agent 的"大脑"，负责理解输入、决策和生成输出。
- **工具（Tools）**：Agent 可以调用的外部功能集合，扩展模型的能力范围。
- **记忆（Memory）**：存储和管理对话历史，提供上下文理解能力。
- **工具执行器（ToolExecutor）**：负责执行工具调用，处理参数和结果。
- **工具使用记录（UsedTool）**：记录已使用的工具信息，便于追踪和分析。

### 工作流程

Agent 的典型工作流程如下：

1. 接收用户输入和系统上下文
2. 利用记忆管理模块检索相关历史信息
3. 通过 LLM 分析信息并决定行动方案
4. 调用适当的工具完成任务步骤
5. 处理工具返回的结果并更新内部状态
6. 生成响应并更新记忆
7. 循环执行直到任务完成

### 架构优势

- **模块化设计**：各组件独立且可替换，便于定制和扩展
- **自主决策**：能够根据上下文动态选择行动方案
- **并行处理**：支持多工具并行调用，提高执行效率
- **流式响应**：支持工具调用过程中的实时反馈
- **防循环机制**：内置深度限制，防止无限递归调用

## 基本 Agent 使用

Odin 框架提供了专为工具调用设计的 `ToolUseAgent` 类，作为构建智能应用的基础。

### 创建简单 Agent

下面是创建一个基本 Agent 的示例：

```php
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

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

// 创建 Agent
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        'calculator' => $calculatorTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 使用 Agent
$userMessage = new UserMessage('帮我计算 23 乘以 45');
$response = $agent->chat($userMessage);
echo $response->getFirstChoice()->getMessage()->getContent();
```

### Agent 的主要配置选项

`ToolUseAgent` 类支持多种配置选项：

```php
// 设置最大工具调用深度（防止无限循环）
$agent->setToolsDepth(30);

// 设置频率惩罚（减少重复内容）
$agent->setFrequencyPenalty(0.5);

// 设置存在惩罚（鼓励多样性）
$agent->setPresencePenalty(0.2);

// 设置业务特定参数
$agent->setBusinessParams([
    'custom_parameter' => 'value',
]);

// 设置工具调用前回调
$agent->setToolCallBeforeEvent(function ($toolCall, $tool) {
    // 可以在此记录工具调用或执行其他操作
    echo "即将调用工具：" . $toolCall->function->name . PHP_EOL;
});
```

## 工具规划和执行

### 工具执行流程

Odin 的 Agent 系统采用基于 LLM 的"思考-行动"模式来规划和执行工具调用：

1. **分析用户请求**：大模型理解用户请求，确定需要使用的工具
2. **生成工具调用**：模型输出包含工具名称、参数等信息的调用指令
3. **参数验证与转换**：验证参数格式并执行必要的类型转换
4. **执行工具调用**：调用工具处理逻辑并获取结果
5. **返回工具结果**：将工具执行结果返回给模型
6. **生成最终响应**：模型整合工具结果生成最终响应

这个流程支持多轮工具调用，直到完成整个任务。

### ToolExecutor 执行器

`ToolExecutor` 是工具执行的核心组件，支持单工具执行和多工具并行执行：

```php
use Hyperf\Odin\Agent\Tool\ToolExecutor;

// 创建执行器
$executor = new ToolExecutor();

// 添加多个工具调用任务
$executor->add(function() use ($weatherTool) {
    return $weatherTool->run(['city' => '北京']);
});

$executor->add(function() use ($calculatorTool) {
    return $calculatorTool->run(['operation' => 'multiply', 'a' => 6, 'b' => 7]);
});

// 设置是否并行执行（默认为并行）
$executor->setParallel(true);

// 执行所有工具调用
$results = $executor->run();
```

### 多工具并行调用

Odin 框架提供了 `MultiToolUseParallelTool` 用于并行执行多个工具调用：

```php
use Hyperf\Odin\Agent\Tool\MultiToolUseParallelTool;

// 创建多工具并行执行器
$multiToolExecutor = new MultiToolUseParallelTool([
    'calculator' => $calculatorTool,
    'weather' => $weatherTool,
    'translate' => $translateTool,
]);

// 执行多个工具调用
$results = $multiToolExecutor->execute([
    'tool_uses' => [
        [
            'recipient_name' => 'tools.calculator',
            'parameters' => [
                'operation' => 'multiply',
                'a' => 6,
                'b' => 7
            ]
        ],
        [
            'recipient_name' => 'tools.weather',
            'parameters' => [
                'city' => '北京'
            ]
        ]
    ]
]);
```

### 工具使用记录

每次工具调用都会被记录在 `UsedTool` 对象中，便于追踪和分析：

```php
// 获取已使用的工具记录
$usedTools = $agent->getUsedTools();

foreach ($usedTools as $tool) {
    echo "工具名称: " . $tool->getName() . PHP_EOL;
    echo "参数: " . json_encode($tool->getArguments()) . PHP_EOL;
    echo "结果: " . json_encode($tool->getResult()) . PHP_EOL;
    echo "执行时间: " . $tool->getElapsedTime() . "秒" . PHP_EOL;
    echo "是否成功: " . ($tool->isSuccess() ? '是' : '否') . PHP_EOL;
    if (!$tool->isSuccess()) {
        echo "错误信息: " . $tool->getErrorMessage() . PHP_EOL;
    }
    echo "---------------" . PHP_EOL;
}
```

## 流式响应支持

Odin 的 Agent 系统支持流式响应，使用户能够实时看到工具执行过程：

```php
// 流式响应示例
$userMessage = new UserMessage('请计算 7 的 3 次方，然后查询今天的天气');
$responses = $agent->chatStreamed($userMessage);

foreach ($responses as $response) {
    $delta = $response->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        flush(); // 立即输出到浏览器
    }
}
```

流式响应在工具调用过程中的输出格式：

1. 模型初始思考和分析
2. 工具调用的决策过程
3. 实时显示工具执行结果
4. 基于工具结果的继续分析
5. 最终综合结论

## 复杂 Agent 实现示例

### 1. 多功能数据处理 Agent

这个示例展示了一个能够处理数据库查询、推荐和计算功能的复杂 Agent：

```php
<?php

namespace App\Agent;

use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

class DataProcessingAgent
{
    private ToolUseAgent $agent;
    
    public function __construct(
        private $model,
        private $logger
    ) {
        // 初始化记忆管理
        $memory = new MemoryManager();
        $memory->setPolicy(new TokenLimitPolicy());
        $memory->addSystemMessage(new SystemMessage(
            '你是一个专业的数据处理助手，能够帮助用户查询数据库、处理数据和提供推荐。'
        ));
        
        // 创建数据库查询工具
        $databaseTool = $this->createDatabaseTool();
        
        // 创建数据处理工具
        $calculatorTool = $this->createCalculatorTool();
        
        // 创建推荐工具
        $recommendTool = $this->createRecommendTool();
        
        // 创建 Agent
        $this->agent = new ToolUseAgent(
            model: $this->model,
            memory: $memory,
            tools: [
                $calculatorTool->getName() => $calculatorTool,
                $databaseTool->getName() => $databaseTool,
                $recommendTool->getName() => $recommendTool,
            ],
            temperature: 0.6,
            logger: $this->logger
        );
    }
    
    public function process(string $query): string
    {
        $userMessage = new UserMessage($query);
        $response = $this->agent->chat($userMessage);
        return $response->getFirstChoice()->getMessage()->getContent();
    }
    
    public function processStream(string $query): \Generator
    {
        $userMessage = new UserMessage($query);
        return $this->agent->chatStreamed($userMessage);
    }
    
    private function createDatabaseTool(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'database_query',
            description: '查询数据库中的用户信息',
            parameters: ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'user_id' => [
                        'type' => 'integer',
                        'description' => '用户ID',
                    ],
                ],
                'required' => ['user_id'],
            ]),
            toolHandler: function ($params) {
                $userId = $params['user_id'];
                
                // 模拟数据库查询结果
                $users = [
                    1 => ['name' => '张三', 'age' => 28, 'interests' => ['科技', '阅读', '电影']],
                    2 => ['name' => '李四', 'age' => 35, 'interests' => ['科幻', '旅行', '音乐']],
                    3 => ['name' => '王五', 'age' => 42, 'interests' => ['运动', '烹饪', '摄影']],
                ];
                
                if (isset($users[$userId])) {
                    return $users[$userId];
                }
                
                return ['error' => '未找到该用户'];
            }
        );
    }
    
    private function createCalculatorTool(): ToolDefinition
    {
        // 计算器工具实现...（与前面例子相同）
    }
    
    private function createRecommendTool(): ToolDefinition
    {
        // 推荐工具实现...
    }
}
```

### 2. 研究助手 Agent

这个 Agent 可以执行复杂的信息收集和分析任务：

```php
<?php

namespace App\Agent;

use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Contract\Memory\MemoryInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use App\Tool\SearchTool;
use App\Tool\FilterTool;
use App\Tool\SummarizeTool;
use App\Tool\TranslateTool;
use Psr\Log\LoggerInterface;

class ResearchAgent
{
    private ToolUseAgent $agent;
    private array $researchState = [];
    
    public function __construct(
        private ModelInterface $model,
        private ?MemoryInterface $memory = null,
        private ?LoggerInterface $logger = null
    ) {
        // 设置记忆策略
        if ($this->memory) {
            $this->memory->setPolicy(new TokenLimitPolicy(['max_tokens' => 8000]));
            
            // 添加系统提示
            $this->memory->addSystemMessage(new SystemMessage(
                '你是一个专业的研究助手，能够执行复杂的信息搜索、筛选和总结任务。你可以使用多种工具来完成用户请求。'
            ));
        }
        
        // 初始化工具集
        $tools = [
            (new SearchTool())->toToolDefinition(),
            (new FilterTool())->toToolDefinition(),
            (new SummarizeTool())->toToolDefinition(),
            (new TranslateTool())->toToolDefinition(),
        ];
        
        // 创建基础 Agent
        $this->agent = new ToolUseAgent(
            model: $model,
            memory: $this->memory,
            tools: $tools,
            logger: $this->logger,
        );
        
        // 设置工具调用回调
        $this->agent->setToolCallBeforeEvent(function ($toolCall, $tool) {
            $this->researchState['last_tool'] = $toolCall->function->name;
            $this->researchState['tool_calls'] = ($this->researchState['tool_calls'] ?? 0) + 1;
        });
    }
    
    public function research(string $query): string
    {
        // 初始化研究状态
        $this->researchState = [
            'query' => $query,
            'status' => 'researching',
            'start_time' => microtime(true),
            'tool_calls' => 0,
        ];
        
        // 构建增强提示
        $enhancedPrompt = $this->buildResearchPrompt($query);
        
        // 执行查询
        $userMessage = new UserMessage($enhancedPrompt);
        $response = $this->agent->chat($userMessage);
        
        // 更新研究状态
        $this->researchState['status'] = 'completed';
        $this->researchState['end_time'] = microtime(true);
        $this->researchState['duration'] = $this->researchState['end_time'] - $this->researchState['start_time'];
        
        $result = $response->getFirstChoice()->getMessage()->getContent();
        $this->researchState['result'] = $result;
        
        return $result;
    }
    
    private function buildResearchPrompt(string $query): string
    {
        return <<<PROMPT
我需要你帮我研究以下主题：{$query}

请按照以下步骤操作：
1. 使用搜索工具获取相关信息
2. 使用过滤工具筛选最相关的内容
3. 如果需要，使用翻译工具处理非中文内容
4. 使用总结工具生成综合报告

请提供全面且客观的研究结果，包含多个信息源的观点。
PROMPT;
    }
    
    public function getResearchState(): array
    {
        return $this->researchState;
    }
    
    public function getUsedTools(): array
    {
        return $this->agent->getUsedTools();
    }
}
```

## 最佳实践

### Agent 设计原则

1. **清晰的职责界定**：每个 Agent 应有明确定义的功能范围和目标
2. **适当的系统提示**：提供清晰、具体的系统提示，指导模型正确使用工具
3. **工具定义精确**：为每个工具提供详细的描述和参数说明
4. **异常处理健壮**：妥善处理工具调用过程中可能出现的各种异常
5. **深度限制合理**：设置合理的工具调用深度，防止无限循环
6. **性能优化意识**：合理使用并行执行和流式响应提高用户体验

### 调试技巧

1. **启用日志记录**：通过提供 Logger 实例获取详细的执行日志
2. **查看工具使用记录**：使用 `getUsedTools()` 方法检查工具调用情况
3. **监控执行时间**：记录各阶段执行时间，识别性能瓶颈
4. **使用回调函数**：通过 `setToolCallBeforeEvent` 设置回调监控工具调用
5. **测试单个工具**：先单独测试每个工具功能再集成到 Agent 中

### 优化建议

1. **并行执行独立任务**：对于相互独立的工具调用，使用并行执行提高效率
2. **选择合适的模型**：针对不同复杂度的任务选择合适的模型版本
3. **优化系统提示**：根据实际使用情况不断调整和优化系统提示
4. **记忆策略调整**：根据对话长度和复杂度选择合适的记忆策略
5. **流式响应体验**：对于长时间的工具调用过程，优先使用流式响应提高用户体验

## 结论

Odin 框架的 Agent 系统提供了构建复杂智能应用的强大基础。通过整合大语言模型、工具调用、记忆管理和状态跟踪，您可以开发出能够执行多步骤任务、利用外部功能并保持上下文理解的智能应用。

无论是简单的工具调用、流式交互体验，还是复杂的多任务协作系统，Odin 的 Agent 架构都能够满足多样化的应用需求。通过遵循本章提供的指南和最佳实践，您可以充分发挥 Agent 系统的潜力，创建出更智能、更高效的应用。 
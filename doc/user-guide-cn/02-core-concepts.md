# 核心概念

> 本章节介绍 Odin 框架的核心概念和设计原则，帮助您深入理解框架的工作原理。

## LLM 模型接口

Odin 框架的核心是对各种大语言模型（LLM）的统一抽象和接口封装。所有模型类都实现了 `ModelInterface` 接口，提供了一致的方法来与不同的模型提供商进行交互。

### 基本接口设计

`ModelInterface` 定义了三个核心方法：

```php
interface ModelInterface
{
    /**
     * 同步聊天接口
     * @param array<MessageInterface> $messages 消息数组
     */
    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionResponse;

    /**
     * 流式聊天接口
     * @param array<MessageInterface> $messages 消息数组
     */
    public function chatStream(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionStreamResponse;
    
    /**
     * 文本补全接口
     */
    public function completions(
        string $prompt,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): TextCompletionResponse;
}
```

### 模型抽象基类

框架提供了 `AbstractModel` 抽象基类，它实现了 `ModelInterface` 接口并提供了通用功能的实现，包括：

- 参数预处理和验证
- 标准化请求和响应格式
- 错误处理和日志记录
- 通用配置项管理

具体的模型类只需要继承这个抽象类并实现特定的方法，如：

```php
abstract protected function getClient(): ClientInterface;
```

### 嵌入接口

除了聊天功能外，Odin 还支持文本嵌入（Embedding）功能，通过 `EmbeddingInterface` 接口定义：

```php
interface EmbeddingInterface
{
    public function embedding(string $input): Embedding;
    
    public function embeddings(array|string $input, ?string $encoding_format = 'float', ?string $user = null): EmbeddingResponse;
    
    public function getModelName(): string;
    
    public function getVectorSize(): int;
}
```

文本嵌入是将文本转换为向量表示的过程，是实现语义搜索和知识检索的基础。

## 消息和会话管理

消息是 LLM 应用程序的基本单位，Odin 框架提供了统一的消息接口和多种消息类型。

### 消息接口

所有消息类型都实现了 `MessageInterface` 接口：

```php
interface MessageInterface
{
    /**
     * 获取消息角色
     */
    public function getRole(): Role;
    
    /**
     * 获取消息内容
     */
    public function getContent(): string;
    
    /**
     * 获取消息唯一标识
     */
    public function getIdentifier(): string;
    
    /**
     * 设置消息唯一标识
     */
    public function setIdentifier(string $identifier): self;
    
    /**
     * 获取业务参数
     */
    public function getParams(): array;
    
    /**
     * 设置业务参数
     */
    public function setParams(array $params): void;
    
    /**
     * 将消息转换为数组
     */
    public function toArray(): array;
    
    /**
     * 从数组创建消息
     */
    public static function fromArray(array $message): self;
}
```

### 消息类型

Odin 支持四种标准消息类型：

1. **SystemMessage**：系统指令消息，用于设置模型的行为和约束
2. **UserMessage**：用户输入消息，表示用户的请求或问题
3. **AssistantMessage**：助手回复消息，表示模型的回答
4. **ToolMessage**：工具调用结果消息，表示工具执行的结果

### 会话结构

一个典型的会话由多个消息组成，通常遵循以下结构：

```php
$messages = [
    new SystemMessage("你是一个友好的AI助手"),
    new UserMessage("你好，请介绍一下自己"),
    new AssistantMessage("你好！我是一个AI助手，很高兴为你服务..."),
    new UserMessage("你能做些什么？")
];
```

会话上下文会被传递给模型，使模型能够理解对话的历史并提供连贯的回复。

## 工具调用机制

工具调用是让 LLM 使用外部功能的机制，是构建强大 Agent 的关键。

### 工具定义

工具通过 `ToolDefinition` 类定义，包含名称、描述、参数和执行器：

```php
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
```

### 工具参数

工具参数通过 `ToolParameters` 类定义，支持JSON Schema标准的定义格式：

- 基本类型：string、number、integer、boolean
- 复合类型：array、object
- 验证规则：必填、枚举、范围、正则表达式等

```php
// 创建工具参数
$parameters = new ToolParameters(
    properties: [
        new ToolParameter(
            name: 'city', 
            type: 'string', 
            description: '要查询天气的城市名称',
            required: true
        ),
        new ToolParameter(
            name: 'date', 
            type: 'string', 
            description: '查询日期，格式为YYYY-MM-DD',
            required: false
        ),
    ],
    type: 'object',
    title: '天气查询参数',
    description: '用于查询天气的参数集合'
);

// 或者使用JSON Schema格式直接创建
$parameters = ToolParameters::fromArray([
    'type' => 'object',
    'properties' => [
        'city' => [
            'type' => 'string',
            'description' => '要查询天气的城市名称',
        ],
        'date' => [
            'type' => 'string',
            'description' => '查询日期，格式为YYYY-MM-DD',
        ],
    ],
    'required' => ['city'],
]);
```

### 工具调用流程

1. 将工具定义传递给模型
2. 模型根据用户输入决定是否调用工具
3. 如果模型决定调用工具，它会生成工具调用请求
4. 框架执行相应的工具函数并获取结果
5. 将结果作为 ToolMessage 返回给模型
6. 模型根据工具执行结果生成最终回复

### 工具验证

`ToolDefinition` 类提供了参数验证机制，可以在执行工具前验证参数是否符合定义：

```php
$params = ['city' => '北京'];
$validationResult = $weatherTool->validateParameters($params);

if ($validationResult['valid']) {
    // 参数有效，执行工具
    $result = $weatherTool->getToolHandler()($params);
} else {
    // 参数无效，处理错误
    $errors = $validationResult['errors'];
    // ...
}
```

## 记忆管理

记忆管理是维护对话上下文和管理长期知识的关键组件。

### 记忆接口

记忆管理通过 `MemoryInterface` 接口定义：

```php
interface MemoryInterface
{
    /**
     * 添加消息到记忆上下文
     */
    public function addMessage(MessageInterface $message): self;
    
    /**
     * 添加系统消息到记忆上下文
     */
    public function addSystemMessage(MessageInterface $message): self;
    
    /**
     * 获取所有普通消息
     */
    public function getMessages(): array;
    
    /**
     * 获取所有系统消息
     */
    public function getSystemMessages(): array;
    
    /**
     * 获取经过策略处理后的所有消息（系统消息+普通消息）
     */
    public function getProcessedMessages(): array;
    
    /**
     * 清空所有消息
     */
    public function clear(): self;
    
    /**
     * 设置记忆策略
     */
    public function setPolicy(PolicyInterface $policy): self;
    
    /**
     * 获取当前记忆策略
     */
    public function getPolicy(): ?PolicyInterface;
    
    /**
     * 应用当前设置的策略处理消息
     */
    public function applyPolicy(): self;
}
```

### 记忆策略

记忆策略决定了如何处理和优化对话历史，通过 `PolicyInterface` 接口定义：

```php
interface PolicyInterface
{
    /**
     * 处理消息列表，返回经过策略处理后的消息列表
     */
    public function process(array $messages): array;
    
    /**
     * 配置策略参数
     */
    public function configure(array $options): self;
}
```

常用的记忆策略包括：

1. **窗口策略**：只保留最近的 N 条消息
2. **令牌限制策略**：根据令牌数限制记忆大小
3. **摘要策略**：使用模型自动总结历史对话
4. **重要性策略**：保留关键信息，丢弃次要信息

### 记忆驱动

记忆驱动决定了记忆的存储方式：

1. **内存驱动**：存储在应用内存中，适合简单应用
2. **Redis驱动**：存储在Redis中，支持持久化和共享（自行实现接口即可）
3. **数据库驱动**：存储在关系型数据库中，适合复杂应用（自行实现接口即可）

## 异常处理机制

Odin 框架提供了结构化的异常处理机制，帮助开发者识别和处理各种错误情况。

### 异常层次结构

- **OdinException**：所有框架异常的基类
  - **LLMException**：与LLM相关的异常基类
    - **LLMApiException**：API调用异常
    - **LLMNetworkException**：网络异常
    - **LLMModelException**：模型调用异常
    - **LLMConfigurationException**：配置异常
  - **InvalidArgumentException**：参数验证异常
  - **ToolParameterValidationException**：工具参数验证异常
  - **RuntimeException**：运行时异常

框架还提供了错误映射和错误处理器，将不同提供商的错误代码和消息标准化，便于统一处理：

```php
// 错误映射示例
$errorMapping = new ErrorMapping(
    source: 'openai_api',
    sourceErrorCode: 'context_length_exceeded',
    targetErrorCode: ErrorCode::CONTEXT_LENGTH_EXCEEDED,
    message: '上下文长度超出限制，请缩短输入或清除历史记录'
);
```

### 异常处理建议

```php
try {
    $response = $model->chat($messages);
} catch (LLMApiException $e) {
    // 处理API调用错误
    logger()->error('API错误: ' . $e->getMessage(), [
        'error_code' => $e->getErrorCode(),
        'model' => $model->getName(),
    ]);
} catch (LLMNetworkException $e) {
    // 处理网络错误
    logger()->error('网络错误: ' . $e->getMessage());
} catch (LLMException $e) {
    // 处理其他LLM相关错误
    logger()->error('LLM错误: ' . $e->getMessage());
} catch (OdinException $e) {
    // 处理其他框架异常
    logger()->error('框架错误: ' . $e->getMessage());
} catch (\Exception $e) {
    // 处理通用异常
    logger()->critical('未知错误: ' . $e->getMessage());
}
```

## 下一步

理解了这些核心概念后，您可以：

- 查看[API参考](./03-api-reference.md)了解详细的类和方法说明
- 学习[模型提供商](./04-model-providers.md)章节了解不同模型的特性
- 探索[工具开发](./05-tool-development.md)章节学习如何创建自定义工具
- 深入[记忆管理](./06-memory-management.md)章节掌握高级记忆技术

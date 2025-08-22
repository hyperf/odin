# API 参考

> 本章节详细说明 Odin 框架的 API 接口及主要类的用法。

## 模型相关接口

### ModelInterface

所有模型实现的基础接口。

```php
namespace Hyperf\Odin\Contract\Model;

interface ModelInterface
{
    /**
     * 同步聊天接口
     * @param array<MessageInterface> $messages 消息数组
     * @param float $temperature 温度参数，控制随机性
     * @param int $maxTokens 最大生成令牌数
     * @param array $stop 停止词
     * @param array $tools 可用工具
     * @param float $frequencyPenalty 频率惩罚参数
     * @param float $presencePenalty 存在惩罚参数
     * @param array $businessParams 业务参数
     * @return ChatCompletionResponse 聊天完成响应
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
     * @param float $temperature 温度参数，控制随机性
     * @param int $maxTokens 最大生成令牌数
     * @param array $stop 停止词
     * @param array $tools 可用工具
     * @param float $frequencyPenalty 频率惩罚参数
     * @param float $presencePenalty 存在惩罚参数
     * @param array $businessParams 业务参数
     * @return ChatCompletionStreamResponse 流式聊天完成响应
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
     * @param string $prompt 提示文本
     * @param float $temperature 温度参数，控制随机性
     * @param int $maxTokens 最大生成令牌数
     * @param array $stop 停止词
     * @param float $frequencyPenalty 频率惩罚参数
     * @param float $presencePenalty 存在惩罚参数
     * @param array $businessParams 业务参数
     * @return TextCompletionResponse 文本补全响应
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

#### 使用示例

```php
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

// 聊天示例
$response = $model->chat([
    new SystemMessage('你是一个助手'),
    new UserMessage('你好'),
], 0.7, 100);

echo $response->getFirstChoice()->getMessage()->getContent();

// 流式聊天示例
$streamResponse = $model->chatStream([
    new SystemMessage('你是一个助手'),
    new UserMessage('你好'),
]);

foreach ($streamResponse->getStreamIterator() as $choice) {
    echo $choice->getMessage()->getContent();
}

// 文本补全示例
$completionResponse = $model->completions('完成下面的句子：人工智能将帮助人类', 0.7, 100);
echo $completionResponse->getFirstChoice()->getText();
```

### EmbeddingInterface

提供文本嵌入功能的接口。

```php
namespace Hyperf\Odin\Contract\Model;

interface EmbeddingInterface
{
    /**
     * 生成单个文本的嵌入向量
     * @param string $input 要嵌入的文本
     * @return Embedding 嵌入结果
     */
    public function embedding(string $input): Embedding;
    
    /**
     * 批量生成文本的嵌入向量
     * @param array|string $input 要嵌入的文本或文本数组
     * @param string|null $encoding_format 编码格式，默认为'float'
     * @param string|null $user 用户标识
     * @return EmbeddingResponse 嵌入响应
     */
    public function embeddings(array|string $input, ?string $encoding_format = 'float', ?string $user = null): EmbeddingResponse;
    
    /**
     * 获取模型名称
     * @return string 模型名称
     */
    public function getModelName(): string;
    
    /**
     * 获取向量维度
     * @return int 向量维度
     */
    public function getVectorSize(): int;
}
```

#### 使用示例

```php
$model = new OpenAIModel('text-embedding-3-small', [
    'api_key' => env('OPENAI_API_KEY'),
], new Logger());

// 单个文本嵌入
$embedding = $model->embedding('这是一段需要转换为向量的文本');
$vector = $embedding->getEmbeddings();

// 批量文本嵌入
$response = $model->embeddings([
    '这是第一段文本',
    '这是第二段文本'
]);

// 访问批量嵌入结果
foreach ($response->getData() as $embedding) {
    $vector = $embedding->getEmbeddings();
    // 处理向量...
}
```

## 消息相关接口

### MessageInterface

所有消息类型实现的基础接口。

```php
namespace Hyperf\Odin\Contract\Message;

interface MessageInterface
{
    /**
     * 获取消息角色
     * @return Role 消息角色
     */
    public function getRole(): Role;

    /**
     * 获取消息内容
     * @return string 消息内容
     */
    public function getContent(): string;

    /**
     * 获取消息唯一标识
     * @return string 唯一标识
     */
    public function getIdentifier(): string;

    /**
     * 设置消息唯一标识
     * @param string $identifier 唯一标识
     * @return self 当前对象
     */
    public function setIdentifier(string $identifier): self;
    
    /**
     * 获取业务参数
     * @return array 业务参数
     */
    public function getParams(): array;
    
    /**
     * 设置业务参数
     * @param array $params 业务参数
     */
    public function setParams(array $params): void;

    /**
     * 将消息转换为数组
     * @return array 数组表示
     */
    public function toArray(): array;
    
    /**
     * 从数组创建消息
     * @param array $message 消息数组
     * @return self 消息对象
     */
    public static function fromArray(array $message): self;
}
```

### 消息类型

#### SystemMessage

系统指令消息，用于设置模型的行为。

```php
use Hyperf\Odin\Message\SystemMessage;

$systemMessage = new SystemMessage('你是一个友好的AI助手');
```

#### UserMessage

用户输入消息，表示用户的问题或请求。

```php
use Hyperf\Odin\Message\UserMessage;

$userMessage = new UserMessage('你好，请介绍一下自己');

// 带有图片的用户消息 (多模态)
$userMessage = new UserMessage('这张图片是什么？', [
    [
        'type' => 'image_url',
        'image_url' => [
            'url' => 'https://example.com/image.jpg'
        ]
    ]
]);
```

#### AssistantMessage

助手回复消息，表示模型的回答。

```php
use Hyperf\Odin\Message\AssistantMessage;

$assistantMessage = new AssistantMessage('你好！我是一个AI助手，很高兴为你服务。');
```

#### ToolMessage

工具调用结果消息，表示工具执行的结果。

```php
use Hyperf\Odin\Message\ToolMessage;

$toolMessage = new ToolMessage(
    'get_weather',
    ['city' => '北京', 'temperature' => '26°C', 'condition' => '晴天']
);
```

## 工具相关接口

### ToolDefinition

工具定义类，用于定义工具的名称、描述、参数和执行器。

```php
namespace Hyperf\Odin\Tool\Definition;

class ToolDefinition
{
    /**
     * 构造函数
     * @param string $name 工具名称
     * @param string $description 工具描述
     * @param null|ToolParameters $parameters 工具参数
     * @param callable|array $toolHandler 执行器函数
     */
    public function __construct(
        string $name,
        string $description = '',
        ?ToolParameters $parameters = null,
        array|callable|\Closure $toolHandler = [],
    );
    
    /**
     * 将工具定义转换为数组
     * @return array 数组表示
     */
    public function toArray(): array;
    
    /**
     * 将工具定义转换为 JSON Schema 格式
     * @return array JSON Schema
     */
    public function toJsonSchema(): array;
    
    /**
     * 验证参数是否符合工具定义
     * @param array $parameters 要验证的参数
     * @return array 验证结果
     */
    public function validateParameters(array $parameters): array;
    
    /**
     * 获取工具处理器
     * @return array|callable|\Closure 工具处理器
     */
    public function getToolHandler(): array|callable|\Closure;
    
    /**
     * 获取工具名称
     * @return string 工具名称
     */
    public function getName(): string;
    
    /**
     * 获取工具描述
     * @return string 工具描述
     */
    public function getDescription(): string;
    
    /**
     * 获取工具参数
     * @return null|ToolParameters 工具参数
     */
    public function getParameters(): ?ToolParameters;
}
```

### ToolParameters

工具参数类，用于定义工具的参数结构。

```php
namespace Hyperf\Odin\Tool\Definition;

class ToolParameters
{
    /**
     * 构造函数
     * @param array $properties 参数属性列表
     * @param string $type 参数类型，默认为 object
     * @param null|string $title 标题
     * @param null|string $description 描述
     */
    public function __construct(
        array $properties = [],
        string $type = 'object',
        ?string $title = null,
        ?string $description = null
    );
    
    /**
     * 将参数转换为数组
     * @return array 数组表示
     */
    public function toArray(): array;
    
    /**
     * 从数组创建参数集合
     * @param array $parameters 参数数组
     * @return self 参数集合
     */
    public static function fromArray(array $parameters): self;
    
    /**
     * 获取参数类型
     * @return string 参数类型
     */
    public function getType(): string;
    
    /**
     * 获取参数属性列表
     * @return array 参数属性列表
     */
    public function getProperties(): array;
    
    /**
     * 添加参数属性
     * @param ToolParameter $property 参数属性
     * @return self 当前对象
     */
    public function addProperty(ToolParameter $property): self;
    
    /**
     * 获取必需参数列表
     * @return array 必需参数列表
     */
    public function getRequired(): array;
}
```

#### 使用示例

```php
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

// 使用JSON Schema格式创建工具参数
$parameters = ToolParameters::fromArray([
    'type' => 'object',
    'properties' => [
        'city' => [
            'type' => 'string',
            'description' => '城市名称',
        ],
        'date' => [
            'type' => 'string',
            'description' => '查询日期，格式为YYYY-MM-DD',
        ],
    ],
    'required' => ['city'],
]);

// 创建工具定义
$weatherTool = new ToolDefinition(
    name: 'get_weather',
    description: '获取指定城市的天气信息',
    parameters: $parameters,
    toolHandler: function (array $args) {
        $city = $args['city'] ?? '北京';
        // 实际应用中可能会调用天气 API
        return [
            'temperature' => '26°C',
            'condition' => '晴天',
            'city' => $city,
            'date' => $args['date'] ?? date('Y-m-d'),
        ];
    }
);

// 参数验证
$params = ['city' => '上海'];
$validationResult = $weatherTool->validateParameters($params);

if ($validationResult['valid']) {
    // 执行工具
    $result = $weatherTool->getToolHandler()($params);
} else {
    // 处理验证错误
    $errors = $validationResult['errors'];
    // ...
}
```

## 记忆管理接口

### MemoryInterface

记忆管理接口，用于管理对话上下文。

```php
namespace Hyperf\Odin\Contract\Memory;

interface MemoryInterface
{
    /**
     * 添加消息到记忆
     * @param MessageInterface $message 要添加的消息
     * @return self 当前对象
     */
    public function addMessage(MessageInterface $message): self;
    
    /**
     * 添加系统消息
     * @param MessageInterface $message 系统消息
     * @return self 当前对象
     */
    public function addSystemMessage(MessageInterface $message): self;
    
    /**
     * 获取所有消息
     * @return array<MessageInterface> 消息数组
     */
    public function getMessages(): array;
    
    /**
     * 获取所有系统消息
     * @return array<MessageInterface> 系统消息数组
     */
    public function getSystemMessages(): array;
    
    /**
     * 获取经过策略处理后的所有消息
     * @return array<MessageInterface> 处理后的消息数组
     */
    public function getProcessedMessages(): array;
    
    /**
     * 清空所有消息
     * @return self 当前对象
     */
    public function clear(): self;
    
    /**
     * 设置记忆策略
     * @param PolicyInterface $policy 记忆策略
     * @return self 当前对象
     */
    public function setPolicy(PolicyInterface $policy): self;
    
    /**
     * 获取当前记忆策略
     * @return null|PolicyInterface 当前策略或null
     */
    public function getPolicy(): ?PolicyInterface;
    
    /**
     * 应用当前设置的策略处理消息
     * @return self 当前对象
     */
    public function applyPolicy(): self;
}
```

### PolicyInterface

记忆策略接口，用于定义如何处理记忆中的消息。

```php
namespace Hyperf\Odin\Contract\Memory;

interface PolicyInterface
{
    /**
     * 处理消息列表，返回经过策略处理后的消息列表
     * @param array<MessageInterface> $messages 原始消息列表
     * @return array<MessageInterface> 处理后的消息列表
     */
    public function process(array $messages): array;
    
    /**
     * 配置策略参数
     * @param array $options 配置选项
     * @return self 当前对象
     */
    public function configure(array $options): self;
}
```

#### 使用示例

```php
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\WindowPolicy;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\AssistantMessage;

// 创建记忆管理器
$memory = new MemoryManager();

// 设置窗口策略，只保留最新的5条消息
$memory->setPolicy(new WindowPolicy(5));

// 添加消息
$memory->addMessage(new UserMessage('你好'));
$memory->addMessage(new AssistantMessage('你好！有什么可以帮助你的吗？'));

// 获取所有经过策略处理的消息
$messages = $memory->getProcessedMessages();

// 清空记忆
$memory->clear();
```

## 响应相关类

### ChatCompletionResponse

聊天完成响应类，表示模型的回复。

```php
namespace Hyperf\Odin\Api\Response;

class ChatCompletionResponse extends AbstractResponse implements Stringable
{
    /**
     * 转换为字符串
     * @return string 响应内容
     */
    public function __toString(): string;
    
    /**
     * 获取响应ID
     * @return string|null 响应ID
     */
    public function getId(): ?string;
    
    /**
     * 获取对象类型
     * @return string|null 对象类型
     */
    public function getObject(): ?string;
    
    /**
     * 获取创建时间戳
     * @return int|null 创建时间戳
     */
    public function getCreated(): ?int;
    
    /**
     * 获取模型名称
     * @return string|null 模型名称
     */
    public function getModel(): ?string;
    
    /**
     * 获取第一个选择
     * @return ChatCompletionChoice|null 第一个选择
     */
    public function getFirstChoice(): ?ChatCompletionChoice;
    
    /**
     * 获取所有选择
     * @return array|null 选择数组
     */
    public function getChoices(): ?array;
    
    /**
     * 获取使用情况
     * @return Usage|null 使用情况
     */
    public function getUsage(): ?Usage;
}
```

### ChatCompletionStreamResponse

流式聊天完成响应类，用于流式输出。

```php
namespace Hyperf\Odin\Api\Response;

class ChatCompletionStreamResponse extends AbstractResponse implements Stringable
{
    /**
     * 转换为字符串
     * @return string 响应描述
     */
    public function __toString(): string;
    
    /**
     * 获取流式迭代器
     * @return \Generator 选择生成器
     */
    public function getStreamIterator(): \Generator;
    
    /**
     * 获取响应ID
     * @return string|null 响应ID
     */
    public function getId(): ?string;
    
    /**
     * 获取对象类型
     * @return string|null 对象类型
     */
    public function getObject(): ?string;
    
    /**
     * 获取创建时间戳
     * @return int|null 创建时间戳
     */
    public function getCreated(): ?int;
    
    /**
     * 获取模型名称
     * @return string|null 模型名称
     */
    public function getModel(): ?string;
    
    /**
     * 获取所有选择
     * @return array 选择数组
     */
    public function getChoices(): array;
}
```

### TextCompletionResponse

文本补全响应类，表示文本补全结果。

```php
namespace Hyperf\Odin\Api\Response;

class TextCompletionResponse extends AbstractResponse
{
    /**
     * 获取第一个选择
     * @return TextCompletionChoice|null 第一个选择
     */
    public function getFirstChoice(): ?TextCompletionChoice;
    
    /**
     * 获取请求是否成功
     * @return bool 成功状态
     */
    public function isSuccess(): bool;
    
    /**
     * 获取原始内容
     * @return string|null 原始内容
     */
    public function getContent(): ?string;
    
    /**
     * 获取响应ID
     * @return string|null 响应ID
     */
    public function getId(): ?string;
    
    /**
     * 获取对象类型
     * @return string|null 对象类型
     */
    public function getObject(): ?string;
    
    /**
     * 获取创建时间
     * @return string|null 创建时间
     */
    public function getCreated(): ?string;
    
    /**
     * 获取所有选择
     * @return array|null 选择数组
     */
    public function getChoices(): ?array;
    
    /**
     * 获取使用情况
     * @return Usage|null 使用情况
     */
    public function getUsage(): ?Usage;
}
```

### EmbeddingResponse

嵌入响应类，表示嵌入结果。

```php
namespace Hyperf\Odin\Api\Response;

class EmbeddingResponse extends AbstractResponse
{
    /**
     * 获取响应对象类型
     * @return string 对象类型
     */
    public function getObject(): string;
    
    /**
     * 获取嵌入数据
     * @return Embedding[] 嵌入数据数组
     */
    public function getData(): array;
    
    /**
     * 获取模型名称
     * @return string|null 模型名称
     */
    public function getModel(): ?string;
    
    /**
     * 转换为数组
     * @return array 数组表示
     */
    public function toArray(): array;
}
```

## Factory 相关类

### ModelFactory

模型工厂类，用于创建模型实例。

```php
namespace Hyperf\Odin\Factory;

class ModelFactory
{
    /**
     * 创建模型实例
     * @param string $implementation 模型实现类
     * @param string $modelName 模型名称/端点
     * @param array $config 模型配置
     * @param null|ModelOptions $modelOptions 模型选项
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     * @return EmbeddingInterface|ModelInterface 模型实例
     */
    public static function create(
        string $implementation,
        string $modelName,
        array $config = [],
        ?ModelOptions $modelOptions = null,
        ?ApiOptions $apiOptions = null,
        ?LoggerInterface $logger = null
    ): EmbeddingInterface|ModelInterface;
}
```

#### 使用示例

```php
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;

// 创建模型选项
$modelOptions = new ModelOptions();
$modelOptions->setChat(true)
    ->setFunctionCall(true)
    ->setMultiModal(true);

// 创建API选项
$apiOptions = new ApiOptions();
$apiOptions->setTimeoutOption('read', 180.0);

// 创建模型实例
$model = ModelFactory::create(
    implementation: AzureOpenAIModel::class,
    modelName: 'gpt-4o-global',
    config: [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    modelOptions: $modelOptions,
    apiOptions: $apiOptions,
    logger: new Logger(),
);
```

### ClientFactory

客户端工厂类，用于创建API客户端实例。

```php
namespace Hyperf\Odin\Factory;

class ClientFactory
{
    /**
     * 创建OpenAI客户端
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     * @return ClientInterface OpenAI客户端
     */
    public static function createOpenAIClient(
        array $config, 
        ?ApiOptions $apiOptions = null, 
        ?LoggerInterface $logger = null
    ): ClientInterface;
    
    /**
     * 创建Azure OpenAI客户端
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     * @return ClientInterface Azure OpenAI客户端
     */
    public static function createAzureOpenAIClient(
        array $config, 
        ?ApiOptions $apiOptions = null, 
        ?LoggerInterface $logger = null
    ): ClientInterface;
    
    /**
     * 创建AWS Bedrock客户端
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     * @return ClientInterface AWS Bedrock客户端
     */
    public static function createAwsBedrockClient(
        array $config, 
        ?ApiOptions $apiOptions = null, 
        ?LoggerInterface $logger = null
    ): ClientInterface;
    
    /**
     * 根据提供商类型创建客户端
     * @param string $provider 提供商类型 (openai, azure_openai, aws_bedrock)
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     * @return ClientInterface 客户端实例
     */
    public static function createClient(
        string $provider, 
        array $config, 
        ?ApiOptions $apiOptions = null, 
        ?LoggerInterface $logger = null
    ): ClientInterface;
}
```

#### 使用示例

```php
use Hyperf\Odin\Factory\ClientFactory;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;

// 创建API选项
$apiOptions = new ApiOptions();
$apiOptions->setTimeoutOption('read', 180.0);

// 创建Azure OpenAI客户端
$client = ClientFactory::createAzureOpenAIClient(
    config: [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    apiOptions: $apiOptions,
    logger: new Logger()
);

// 使用工厂方法创建客户端
$client = ClientFactory::createClient(
    provider: 'aws_bedrock',
    config: [
        'access_key' => env('AWS_ACCESS_KEY_ID'),
        'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    apiOptions: $apiOptions,
    logger: new Logger()
);
```

## 下一步

详细了解了 API 后，您可以：

- 查看[模型提供商](./04-model-providers.md)章节，了解不同模型的特性和配置
- 学习[工具开发](./05-tool-development.md)章节，创建自定义工具
- 深入[记忆管理](./06-memory-management.md)章节，掌握高级记忆技术
- 探索[向量存储](./07-vector-storage.md)章节，了解如何进行知识检索

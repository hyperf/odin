# Frequently Asked Questions

> This chapter lists common questions and solutions that you may encounter when using the Odin framework.

## 常见错误

### 1. 模型连接错误

**问题**: 无法连接到 LLM 服务提供商，出现超时或连接被拒绝错误。

**原因**: 
- API 密钥错误或过期
- 网络连接问题
- 服务提供商暂时不可用
- 防火墙或代理设置阻止了连接

**解决方案**:
```php
// 检查 API 密钥是否正确设置
$model = new AzureOpenAIModel(
    'gpt-4o-global',
    [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'), // 确保环境变量已正确设置
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    new Logger(),
);

// 设置更长的超时时间
$model = ModelFactory::create(
    implementation: AzureOpenAIModel::class,
    modelName: 'gpt-4o-global',
    config: [...],
    apiOptions: ApiOptions::fromArray([
        'timeout' => [
            'connection' => 10.0,  // 增加连接超时时间
            'read' => 300.0,       // 增加读取超时时间
        ],
    ]),
    logger: new Logger()
);
```

### 2. Stream Response Connection Failed in Coroutine Context

**Problem**: When running in Hyperf framework's coroutine environment (such as Command with `$coroutine = true`), stream responses fail with "Connection refused" error, but work normally in non-coroutine context.

**Causes**: 
- Swoole compiled with an older OpenSSL version may have compatibility issues with cURL extension when handling HTTPS connections in coroutine context
- cURL handler may not correctly handle SSL/TLS connections in coroutine environment

**Solutions**:

Starting from Odin v1.x.x, the framework **automatically detects coroutine context** and switches to a compatible HTTP handler. If you encounter this issue:

```php
// Solution 1: Let the framework handle automatically (Recommended)
// Using default 'auto' configuration, framework will auto-detect coroutine and use stream handler
$model = new DoubaoModel(
    'deepseek-r1-250120',
    [
        'api_key' => 'sk-xxx',
        'base_url' => 'https://api.example.com/v1',
    ],
    new Logger(),
);
// Will automatically use stream handler in coroutine context

// Solution 2: Explicitly specify stream handler
$model->setApiRequestOptions(new ApiOptions([
    'http_handler' => 'stream',  // Force use of stream handler
]));

// Solution 3: Global configuration via environment variable
// Set in .env file
// ODIN_HTTP_HANDLER=stream

// Solution 4: Set in configuration file (for specific models)
// config/autoload/odin.php
return [
    'models' => [
        'deepseek-r1' => [
            // ...
            'api_options' => [
                'http_handler' => 'stream',
            ],
        ],
    ],
];
```

**Notes**:
- Stream handler is a pure PHP implementation, does not depend on cURL extension, and is more stable in coroutine environments
- Auto-detection mechanism checks for `Swoole\Coroutine` and `Hyperf\Engine\Coroutine`
- You can also explicitly specify stream handler in non-coroutine environments if needed

### 3. 模型响应格式错误

**问题**: 模型返回的响应格式与预期不符，导致解析错误。

**原因**:
- 提示词（Prompt）设计不当
- 模型版本变更
- 服务提供商 API 更新

**解决方案**:
```php
// 使用更明确的系统消息来引导模型
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个专业的 AI 助手。请遵循以下约束：1. 输出必须是有效的 JSON 格式; 2. 回答必须简明扼要; 3. 严格按照用户的指令进行回答。'));

// 尝试处理不同的响应格式
try {
    $response = $model->chat($messages);
    $message = $response->getFirstChoice()->getMessage();
} catch (JsonException $e) {
    // 记录日志并尝试其他解析方法
    $logger->error('JSON 解析错误：' . $e->getMessage());
    // 尝试使用字符串方法处理...
}
```

### 4. 工具调用失败

**问题**: 工具调用返回错误或未按预期执行。

**原因**:
- 工具参数格式不正确
- 工具实现中的错误
- 模型未正确理解工具的用法

**解决方案**:
```php
// 添加参数验证
$calculatorTool = new ToolDefinition(
    name: 'calculator',
    description: '用于执行基本数学运算的计算器工具',
    parameters: ToolParameters::fromArray([...]),
    toolHandler: function ($params) {
        // 添加参数验证
        if (!isset($params['operation']) || !isset($params['a']) || !isset($params['b'])) {
            return ['error' => '缺少必要参数'];
        }
        
        if (!in_array($params['operation'], ['add', 'subtract', 'multiply', 'divide'])) {
            return ['error' => '无效的操作类型'];
        }
        
        // 其余代码...
    }
);

// 使用 try-catch 包装工具调用
$agent = new ToolUseAgent($model, $memory, [$calculatorTool]);
try {
    $result = $agent->useToolWithRetry(new UserMessage('请计算 123 乘以 456...'));
} catch (Exception $e) {
    // 处理异常并提供友好的错误信息
    echo "工具调用失败：" . $e->getMessage();
}
```

### 5. 内存溢出错误

**问题**: 处理大量对话历史或大文档时出现内存溢出错误。

**原因**:
- 对话历史过长
- 处理过大的文件或数据

**解决方案**:
```php
// 使用对话历史管理
$memory = new MemoryManager([
    'max_messages' => 20, // 限制保存的消息数量
    'summarize_when_full' => true, // 当超出限制时自动总结历史消息
]);

// 使用分块处理大文档
$splitter = new RecursiveCharacterTextSplitter([
    'chunk_size' => 1000,
    'chunk_overlap' => 100,
]);
$texts = $splitter->splitDocuments($largeDocument);

// 批处理向量化
$batchSize = 10;
$batches = array_chunk($texts, $batchSize);
foreach ($batches as $batch) {
    $vectorStore->addDocuments($batch, $embeddings);
    // 可选：在批次间添加短暂暂停
    usleep(500000); // 暂停 0.5 秒
}
```

### 6. 授权和认证错误

**问题**: 授权失败，无法访问 LLM 服务。

**原因**:
- API 密钥权限不足
- 订阅计划限制
- 访问令牌过期

**解决方案**:
```php
// 实现错误重试机制
$maxRetries = 3;
$retryDelay = 1; // 秒

$attempt = 0;
$success = false;

while (!$success && $attempt < $maxRetries) {
    try {
        $response = $model->chat($messages);
        $success = true;
    } catch (AuthorizationException $e) {
        $attempt++;
        $logger->warning("授权失败，尝试第 {$attempt} 次重试");
        
        if ($attempt < $maxRetries) {
            // 可能需要刷新令牌
            refreshApiToken(); // 实现刷新令牌的函数
            sleep($retryDelay * $attempt); // 指数退避
        } else {
            throw new Exception("授权失败，已达到最大重试次数: " . $e->getMessage());
        }
    }
}
```

## 性能问题

### 1. 响应时间过长

**问题**: LLM 响应时间过长，影响用户体验。

**原因**:
- 提示词（Prompt）过长
- 模型处理能力有限
- 网络延迟
- 服务提供商负载过高

**解决方案**:
```php
// 使用流式输出提升用户体验
$model->chatStream($messages, function($chunk) {
    echo $chunk;
    ob_flush();
    flush();
});

// 优化提示词长度
$userMessage = new UserMessage(substr($longText, 0, 2000) . '...');

// 使用更快的模型进行初步回应
$fastModel = new AzureOpenAIModel('gpt-3.5-turbo', [...]);
$initialResponse = $fastModel->chat([new UserMessage('给出简短初步回答：' . $query)]);
echo "初步回答：" . $initialResponse->getFirstChoice()->getMessage()->getContent();

// 同时在后台使用更强大的模型生成完整回答
// ...
```

### 2. 向量检索效率低下

**问题**: 向量检索操作耗时较长。

**原因**:
- 向量数据库配置不当
- 检索参数设置不当
- 索引效率问题

**解决方案**:
```php
// 优化向量存储配置
$vectorStore = new MilvusVectorStore([
    'host' => 'localhost',
    'port' => '19530',
    'collection_name' => 'documents',
    'index_type' => 'IVF_FLAT', // 选择合适的索引类型
    'metric_type' => 'IP',      // 内积相似度
    'index_params' => [
        'nlist' => 1024        // 索引聚类数量
    ],
    'search_params' => [
        'nprobe' => 16         // 优化搜索参数
    ]
]);

// 限制检索结果数量
$results = $vectorStore->similaritySearch($query, 5); // 只返回最相关的5条结果

// 使用缓存减少重复检索
$cacheKey = md5($query);
if ($cache->has($cacheKey)) {
    $results = $cache->get($cacheKey);
} else {
    $results = $vectorStore->similaritySearch($query, 5);
    $cache->set($cacheKey, $results, 3600); // 缓存1小时
}
```

### 3. 内存占用过高

**问题**: 应用程序内存占用过高，特别是在处理大量请求时。

**原因**:
- 未及时释放不需要的资源
- 大型对象（如向量或嵌入）长时间保存在内存中
- PHP 配置不当

**解决方案**:
```php
// 处理完成后显式清理内存
$memory = null;
$embeddings = null;
gc_collect_cycles();

// 分批处理大量数据
$batchSize = 100;
$batches = array_chunk($largeDataset, $batchSize);
foreach ($batches as $batch) {
    processBatch($batch);
    // 处理完一批后清理内存
    gc_collect_cycles();
}

// 使用生成器处理大量数据
function getDocuments($filePath) {
    $handle = fopen($filePath, 'r');
    while (($line = fgets($handle)) !== false) {
        yield json_decode($line, true);
    }
    fclose($handle);
}

// 使用生成器
foreach (getDocuments('large_file.jsonl') as $doc) {
    processDocument($doc);
}
```

### 4. 并发处理能力有限

**问题**: 在高并发场景下，处理能力受限，响应时间增加。

**原因**:
- PHP 单线程特性
- 对 LLM API 的调用是同步的
- 资源限制

**解决方案**:
```php
// 使用队列系统处理异步任务
// 在 Laravel 或 Hyperf 中使用队列
$job = new ProcessLLMRequestJob($query);
dispatch($job);

// 使用 Swoole 协程处理并发请求
use Swoole\Coroutine\WaitGroup;
use Swoole\Coroutine;

$wg = new WaitGroup();
$results = [];

foreach ($queries as $index => $query) {
    $wg->add();
    Coroutine::create(function() use ($wg, $model, $query, &$results, $index) {
        try {
            $results[$index] = $model->chat([new UserMessage($query)]);
        } catch (Exception $e) {
            $results[$index] = ['error' => $e->getMessage()];
        }
        $wg->done();
    });
}

$wg->wait();
```

## 兼容性问题

### 1. PHP 版本兼容性

**问题**: 在不同 PHP 版本下出现兼容性问题。

**原因**:
- 使用了特定 PHP 版本的特性
- 依赖库版本冲突

**解决方案**:
```php
// 在代码中检查 PHP 版本
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('需要 PHP 8.0 或更高版本');
}

// 使用兼容性函数
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

// 在 composer.json 中明确指定依赖版本
// "require": {
//     "php": ">=8.0",
//     "guzzlehttp/guzzle": "^7.0",
//     ...
// }
```

### 2. 不同 LLM 提供商的 API 差异

**问题**: 在不同的 LLM 提供商之间切换时出现兼容性问题。

**原因**:
- API 格式和参数差异
- 功能支持差异
- 错误处理方式不同

**解决方案**:
```php
// 使用适配器模式处理不同提供商
interface LLMProviderInterface {
    public function chat(array $messages);
    public function supports(string $feature): bool;
}

class OpenAIProvider implements LLMProviderInterface {
    // 实现 OpenAI 特定的逻辑
}

class AzureProvider implements LLMProviderInterface {
    // 实现 Azure 特定的逻辑
}

// 使用工厂模式创建合适的提供商
$provider = ProviderFactory::create(env('LLM_PROVIDER', 'openai'));

// 检查特定功能是否支持
if ($provider->supports('function_calling')) {
    // 使用函数调用功能
} else {
    // 使用替代方案
}
```

### 3. 框架整合问题

**问题**: 将 Odin 与不同 PHP 框架（如 Laravel、Symfony、Hyperf）整合时遇到问题。

**原因**:
- 依赖注入方式不同
- 配置管理方式不同
- 异常处理机制不同

**解决方案**:
```php
// Laravel 集成示例
// 在 ServiceProvider 中注册服务
class OdinServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('odin.model', function ($app) {
            return new AzureOpenAIModel(
                config('odin.model_name'),
                config('odin.model_config'),
                new Logger()
            );
        });
    }
}

// Hyperf 集成示例
// 在依赖注入容器中注册
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;

$container = new Container((new DefinitionSourceFactory())());
$container->define(AzureOpenAIModel::class, function () {
    return new AzureOpenAIModel(
        env('MODEL_NAME'),
        [
            'api_key' => env('API_KEY'),
            // ...
        ],
        new Logger()
    );
});
```

### 4. 扩展和插件兼容性

**问题**: 自定义扩展和第三方插件与 Odin 核心功能冲突。

**原因**:
- 命名空间冲突
- 接口不兼容
- 功能重复

**解决方案**:
```php
// 使用接口和事件系统确保扩展兼容性
// 定义清晰的扩展点接口
interface ToolInterface {
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $params): array;
}

// 实现自定义工具
class MyCustomTool implements ToolInterface {
    // 实现接口方法
}

// 注册自定义工具
$toolRegistry->register(new MyCustomTool());

// 使用事件系统允许扩展监听和修改核心功能
$eventDispatcher->dispatch(new BeforeModelCallEvent($messages));
$response = $model->chat($messages);
$eventDispatcher->dispatch(new AfterModelCallEvent($response));
```

## 报错代码参考

以下是常见错误的完整报错信息和解决方法：

### API 密钥错误

```
Fatal error: Uncaught GuzzleHttp\Exception\ClientException: Client error: `POST https://api.example.com/v1/chat/completions` resulted in a `401 Unauthorized` response: {"error":{"message":"Incorrect API key provided. You can find your API key at https://example.com/account.","type":"invalid_request_error","param":null,"code":"invalid_api_key"}} in /path/to/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php:113
```

**解决方法**：检查并更新 API 密钥，确保环境变量正确设置。

### 参数格式错误

```
Fatal error: Uncaught GuzzleHttp\Exception\ClientException: Client error: `POST https://api.example.com/v1/chat/completions` resulted in a `400 Bad Request` response: {"error":{"message":"Invalid request: 'messages' must be an array of message objects.","type":"invalid_request_error","param":"messages","code":"invalid_parameter"}} in /path/to/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php:113
```

**解决方法**：检查参数格式，确保消息数组符合 API 要求。

### 超时错误

```
Fatal error: Uncaught GuzzleHttp\Exception\ConnectException: cURL error 28: Operation timed out after 30000 milliseconds with 0 bytes received in /path/to/vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php:210
```

**解决方法**：增加超时设置，或者检查网络连接。

### 配额限制错误

```
Fatal error: Uncaught GuzzleHttp\Exception\ClientException: Client error: `POST https://api.example.com/v1/chat/completions` resulted in a `429 Too Many Requests` response: {"error":{"message":"Rate limit reached for GPT-4 in organization org-xxxxx. Limit: 10 / min. Please try again in 6s.","type":"rate_limit_error","param":null,"code":"rate_limit_exceeded"}} in /path/to/vendor/guzzlehttp/guzzle/src/Exception/RequestException.php:113
```

**解决方法**：实现请求限流，或升级服务计划以增加配额。

## 最佳实践建议

1. **始终使用环境变量存储敏感信息**，如 API 密钥和端点 URL。

2. **实现请求重试机制**，尤其是对 LLM API 的调用，以处理临时错误。

3. **使用日志记录所有 API 调用**，便于排查问题和优化性能。

4. **定期更新依赖库**，确保安全性和兼容性。

5. **为生产环境使用缓存系统**，减少重复请求。

6. **对长文本输入进行适当截断**，避免超出模型的上下文窗口。

7. **为复杂场景实现异步处理**，提高系统整体吞吐量。

---

在下一章中，我们将提供附录信息，包括术语表、参考资料、更新日志和贡献者名单。

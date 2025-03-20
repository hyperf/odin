# 测试和调试

## 单元测试编写方法

Odin 框架鼓励使用单元测试来确保各组件的质量和稳定性。框架基于 PHPUnit 提供了全面的测试支持。

### 测试环境配置

在 Odin 项目中进行单元测试，首先需要正确配置测试环境：

1. **安装依赖**：确保已安装 PHPUnit 和 Mockery 等测试工具
   ```bash
   composer require --dev phpunit/phpunit mockery/mockery
   ```

2. **配置 PHPUnit**：项目根目录下的 `phpunit.xml` 文件已预配置，包含基本测试设置

3. **创建测试环境配置**：创建 `.env.testing` 文件用于测试环境，可以配置测试专用的 API 密钥和其他设置

### 测试基类

Odin 提供了测试基类，简化测试编写：

```php
<?php

namespace HyperfTest\Odin\Cases;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class AbstractTestCase extends TestCase
{
    /**
     * 调用对象的非公共方法.
     *
     * @param object $object 要调用方法的对象
     * @param string $method 方法名称
     * @param mixed ...$args 传递给方法的参数
     * @return mixed 方法的返回值
     */
    protected function callNonpublicMethod(object $object, string $method, ...$args)
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        return $reflectionMethod->invoke($object, ...$args);
    }
    
    protected function getNonpublicProperty(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $reflectionProperty = $reflection->getProperty($property);
        return $reflectionProperty->getValue($object);
    }
    
    protected function setNonpublicPropertyValue(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setValue($object, $value);
    }
}
```

### 模型测试

测试 LLM 模型的集成或模拟调用：

```php
<?php

namespace HyperfTest\Odin\Cases\Model;

use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass(OpenAIModel::class)]
class OpenAIModelTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试 getApiVersionPath 方法.
     */
    public function testGetApiVersionPath()
    {
        $model = new OpenAIModel('gpt-3.5-turbo', []);

        $apiVersionPath = $this->callNonpublicMethod($model, 'getApiVersionPath');

        $this->assertEquals('v1', $apiVersionPath);
    }

    /**
     * 测试 getClient 方法.
     */
    public function testGetClient()
    {
        // 使用 Mockery 替换 ClientFactory::createOpenAIClient 方法
        $clientMock = Mockery::mock(ClientInterface::class);

        $clientFactoryMock = Mockery::mock('alias:' . ClientFactory::class);
        $clientFactoryMock->shouldReceive('createOpenAIClient')
            ->once()
            ->withArgs(function ($config, $apiOptions, $logger) {
                // 验证 base_url 是否包含 API 版本路径
                return isset($config['base_url']) && str_contains($config['base_url'], '/v1');
            })
            ->andReturn($clientMock);

        $model = new OpenAIModel('gpt-3.5-turbo', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com',
        ]);

        $client = $this->callNonpublicMethod($model, 'getClient');

        $this->assertSame($clientMock, $client);
    }
}
```

### 工具测试

测试自定义工具的功能：

```php
<?php

namespace HyperfTest\Odin\Cases\Tool;

use Hyperf\Odin\Exception\ToolParameterValidationException;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

class AbstractToolTest extends ToolBaseTestCase
{
    /**
     * 测试工具定义获取.
     */
    public function testGetDefinition(): void
    {
        $tool = new class extends AbstractTool {
            protected string $name = 'simple_tool';

            protected string $description = '简单工具';

            protected function handle(array $parameters): array
            {
                return $parameters;
            }
        };
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        $definition = $tool->toToolDefinition();

        $this->assertInstanceOf(ToolDefinition::class, $definition);
        $this->assertEquals('simple_tool', $definition->getName());
        $this->assertEquals('简单工具', $definition->getDescription());
    }
    
    /**
     * 测试工具调用验证逻辑.
     */
    public function testValidateParameters(): void
    {
        $tool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                // 简单的业务逻辑
                $name = $parameters['name'] ?? '';
                $age = $parameters['age'] ?? 0;

                if ($name === '') {
                    throw new ToolParameterValidationException('工具参数验证失败: name 不能为空', ['name' => ['不能为空']]);
                }

                return [
                    'processedName' => $name,
                    'nextYearAge' => $age + 1,
                ];
            }
        };
        $tool->setName('test_tool');
        $tool->setDescription('测试工具');
        $tool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '名称',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                ],
            ],
            'required' => ['name'],
        ]));

        // 有效参数测试
        $validParams = ['name' => '测试', 'age' => 25];
        $result = $tool->run($validParams);
        $this->assertIsArray($result);

        // 无效参数测试 - 缺少必填项
        try {
            $invalidParams = ['age' => 25]; // 缺少 name
            $tool->run($invalidParams);
            $this->fail('缺少必填参数应该抛出异常');
        } catch (ToolParameterValidationException $e) {
            $this->assertStringContainsString('工具参数验证失败', $e->getMessage());
            $this->assertStringContainsString('name', $e->getMessage());
        }
    }
}
```

### Agent 测试

测试 Agent 的决策和工具调用逻辑：

```php
<?php

namespace HyperfTest\Odin\Cases\Agent\Tool;

use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Contract\Memory\MemoryInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Psr\Log\LoggerInterface;

class ToolUseAgentTest extends AbstractTestCase
{
    private $model;
    private $memory;
    private $logger;
    private array $tools = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟的模型实例
        $this->model = Mockery::mock(ModelInterface::class);

        // 创建模拟的内存管理器
        $this->memory = Mockery::mock(MemoryInterface::class);
        $this->memory->shouldReceive('addMessage')->andReturnSelf();
        $this->memory->shouldReceive('applyPolicy')->andReturnSelf();
        $this->memory->shouldReceive('getProcessedMessages')->andReturn([]);

        // 创建模拟的日志记录器
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('info')->andReturnNull();
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->logger->shouldReceive('warning')->andReturnNull();

        // 创建测试用工具
        $this->tools = [
            'calculator' => new ToolDefinition(
                name: 'calculator',
                description: '计算器工具',
                toolHandler: function ($params) {
                    return ['result' => $params['a'] + $params['b']];
                }
            ),
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testToolCallSuccess()
    {
        // 设置模型返回工具调用响应
        $toolCall = new ToolCall();
        $toolCall->id = 'call_123';
        $toolCall->type = 'function';
        $toolCall->function = new \stdClass();
        $toolCall->function->name = 'calculator';
        $toolCall->function->arguments = json_encode(['a' => 1, 'b' => 2]);
        
        $assistantMessage = new AssistantMessage('我将使用计算器');
        $assistantMessage->setToolCalls([$toolCall]);
        
        $choice = new ChatCompletionChoice();
        $choice->message = $assistantMessage;
        
        // 设置模型返回工具调用响应后的最终响应
        $finalAssistantMessage = new AssistantMessage('计算结果是 3');
        $finalChoice = new ChatCompletionChoice();
        $finalChoice->message = $finalAssistantMessage;
        
        $this->model->shouldReceive('chat')
            ->twice()
            ->andReturn(
                new ChatCompletionResponse(['choices' => [$choice]]),
                new ChatCompletionResponse(['choices' => [$finalChoice]])
            );
        
        // 创建 Agent 并执行测试
        $agent = new ToolUseAgent(
            model: $this->model,
            tools: $this->tools,
            memory: $this->memory,
            logger: $this->logger
        );
        
        $response = $agent->chat(new UserMessage('请计算 1+2'));
        
        // 验证结果
        $this->assertEquals('计算结果是 3', $response->choices[0]->message->getContent());
        
        // 验证工具是否被调用
        $usedTools = $agent->getUsedTools();
        $this->assertCount(1, $usedTools);
        $this->assertEquals('calculator', $usedTools[0]->name);
        $this->assertEquals(['a' => 1, 'b' => 2], $usedTools[0]->arguments);
    }
}
```

### 记忆管理测试

测试记忆管理和策略：

```php
<?php

namespace HyperfTest\Odin\Memory;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Memory\Driver\InMemoryDriver;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MemoryManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testConstructWithoutDriver()
    {
        $manager = new MemoryManager();

        $this->assertInstanceOf(MemoryManager::class, $manager);
        // 验证默认使用 InMemoryDriver
        $reflection = new ReflectionClass($manager);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $driver = $property->getValue($manager);

        $this->assertInstanceOf(InMemoryDriver::class, $driver);
    }

    public function testGetProcessedMessagesWithPolicy()
    {
        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
            new UserMessage('消息3'),
            new UserMessage('消息4'),
            new UserMessage('消息5'),
        ];

        $mockPolicy = Mockery::mock(PolicyInterface::class);
        $mockPolicy->shouldReceive('apply')
            ->once()
            ->withMockery(Mockery::type('array'))
            ->andReturn([
                $messages[3],
                $messages[4],
            ]); // 策略只保留最后2条消息

        $mockDriver = Mockery::mock(DriverInterface::class);
        $mockDriver->shouldReceive('getMessages')
            ->once()
            ->andReturn($messages);

        $manager = new MemoryManager($mockDriver);
        $manager->setPolicy($mockPolicy);

        $processedMessages = $manager->getProcessedMessages();

        $this->assertCount(2, $processedMessages);
        $this->assertSame($messages[3], $processedMessages[0]);
        $this->assertSame($messages[4], $processedMessages[1]);
    }
}
```

## 日志记录和分析

Odin 框架内置了全面的日志系统，帮助开发者记录、跟踪和分析应用行为。

### 日志配置

Odin 使用 PSR-3 兼容的日志系统，默认集成了 Monolog：

```php
// 在 config/autoload/logger.php 中配置
return [
    'default' => [
        'handler' => [
            'class' => \Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => BASE_PATH . '/runtime/logs/hyperf.log',
                'level' => \Monolog\Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class' => \Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => "[%datetime%] [%channel%] [%level_name%] %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'odin' => [
        'handler' => [
            'class' => \Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => BASE_PATH . '/runtime/logs/odin.log',
                'level' => \Monolog\Logger::INFO,
                'maxFiles' => 7,
            ],
        ],
        'formatter' => [
            'class' => \Monolog\Formatter\JsonFormatter::class,
        ],
    ],
];
```

### 使用日志记录器

在 Odin 的各个组件中使用日志记录器：

```php
<?php

use Hyperf\Odin\Logger;
use Psr\Log\LoggerInterface;

class MyService
{
    private LoggerInterface $logger;
    
    public function __construct(?LoggerInterface $logger = null)
    {
        // 如果没有传入日志记录器，使用默认实现
        $this->logger = $logger ?? new Logger();
    }
    
    public function process($data)
    {
        try {
            $this->logger->info('开始处理数据', ['data_id' => $data->id]);
            
            // 处理逻辑...
            
            $this->logger->info('数据处理完成', ['result' => 'success']);
        } catch (\Throwable $e) {
            $this->logger->error('数据处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
```

### 日志分析

Odin 的日志通常记录以下类型的信息：

1. **API 调用**：所有与 LLM 提供商的交互
2. **工具调用**：工具的调用参数和结果
3. **记忆操作**：记忆策略的应用和优化
4. **异常情况**：各种错误和异常

分析日志的工具和方法：

- **日志聚合系统**：如 ELK Stack（Elasticsearch, Logstash, Kibana）
- **自定义日志分析脚本**：使用 Python 或 PHP 分析日志模式
- **可视化工具**：将日志数据转换为图表和仪表板

示例日志查询和分析命令：

```bash
# 查找错误日志
grep -i "error" runtime/logs/odin.log

# 分析特定时间段的日志
sed -n '/2023-12-01 10:00:00/,/2023-12-01 11:00:00/p' runtime/logs/odin.log

# 统计不同类型的日志条目
grep -o '"level":"[^"]*"' runtime/logs/odin.log | sort | uniq -c
```

## 常见问题排查方法

在使用 Odin 框架进行开发时，可能会遇到各种问题。以下是常见问题的排查方法。

### API 连接问题

当出现 API 连接问题时：

1. **检查网络连接**：确认服务器能够访问 LLM 提供商的 API
   ```bash
   curl -v https://api.openai.com/v1/chat/completions
   ```

2. **验证 API 密钥**：确认 API 密钥有效且权限正确
   ```php
   // 使用简单脚本测试 API 密钥
   $ch = curl_init('https://api.openai.com/v1/chat/completions');
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_HTTPHEADER, [
       'Content-Type: application/json',
       'Authorization: Bearer ' . env('OPENAI_API_KEY'),
   ]);
   curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
       'model' => 'gpt-3.5-turbo',
       'messages' => [
           ['role' => 'user', 'content' => '测试消息'],
       ],
   ]));
   $response = curl_exec($ch);
   $error = curl_error($ch);
   curl_close($ch);
   
   var_dump($response, $error);
   ```

3. **检查代理设置**：如果使用代理访问 API，确认代理配置正确

### 模型响应问题

当模型返回意外响应时：

1. **启用详细日志**：记录完整的请求和响应
   ```php
   $model = new OpenAIModel(
       modelName: 'gpt-3.5-turbo',
       config: [
           'api_key' => $apiKey,
           'debug' => true, // 启用调试模式
       ]
   );
   ```

2. **检查提示设计**：评估系统提示和用户消息的有效性
   ```php
   // 记录和评估提示
   $logger->debug('发送给模型的消息', [
       'messages' => array_map(fn($m) => [
           'role' => $m->getRole(),
           'content' => $m->getContent(),
       ], $messages),
   ]);
   ```

3. **使用结构化输出**：指定明确的输出格式
   ```php
   $message = new UserMessage(<<<EOT
   请按照以下 JSON 格式返回结果：
   {
       "分析": "...",
       "建议": "...",
       "评分": 1-10
   }
   EOT);
   ```

### 工具调用问题

当工具调用失败时：

1. **检查参数验证**：确认传递给工具的参数符合 Schema 定义
   ```php
   // 临时禁用参数验证进行调试
   $tool->setValidateParameters(false);
   $result = $tool->run($parameters);
   
   // 手动验证参数
   try {
       $validator = new ToolParameterValidator();
       $validator->validate($parameters, $tool->getParameters()->toArray());
   } catch (Exception $e) {
       var_dump($e->getMessage());
   }
   ```

2. **追踪工具执行**：记录工具执行的每个步骤
   ```php
   $agent->setToolCallBeforeEvent(function ($toolCall, $tool) use ($logger) {
       $logger->debug('工具调用开始', [
           'tool' => $toolCall->function->name,
           'arguments' => json_decode($toolCall->function->arguments, true),
       ]);
   });
   ```

3. **检查工具注册**：确认工具已正确注册到 Agent
   ```php
   // 列出已注册的工具
   $tools = $agent->getTools();
   var_dump(array_keys($tools));
   ```

### 记忆管理问题

当记忆管理不如预期工作时：

1. **检查记忆策略**：验证策略是否正确应用
   ```php
   // 检查策略应用后的消息
   $memory = new MemoryManager();
   $memory->setPolicy(new LimitCountPolicy(10));
   
   // 添加测试消息
   for ($i = 0; $i < 20; $i++) {
       $memory->addMessage(new UserMessage("消息 {$i}"));
   }
   
   // 查看处理后的消息
   $processed = $memory->getProcessedMessages();
   var_dump(count($processed));
   ```

2. **检查记忆驱动**：确认记忆存储驱动工作正常
   ```php
   // 创建自定义内存驱动测试
   $driver = new InMemoryDriver();
   $memory = new MemoryManager($driver);
   
   $memory->addMessage(new UserMessage('测试消息'));
   $messages = $memory->getMessages();
   
   var_dump(count($messages));
   ```

## 性能分析工具使用指南

Odin 框架在处理复杂 LLM 应用时，需要关注性能优化。以下是一些性能分析工具和技术。

### 请求分析

监控和分析 API 请求性能：

```php
<?php

use Hyperf\Utils\Stopwatch;

class RequestProfiler
{
    public function profileApiCall(callable $apiCall, array $context = [])
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('api_call');
        
        try {
            $result = $apiCall();
            $event = $stopwatch->stop('api_call');
            
            // 记录性能指标
            $this->logPerformance([
                'duration' => $event->getDuration(), // 毫秒
                'memory' => $event->getMemory(), // 字节
                'context' => $context,
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            $stopwatch->stop('api_call');
            throw $e;
        }
    }
    
    private function logPerformance(array $data)
    {
        // 记录到日志、数据库或监控系统
    }
}

// 使用示例
$profiler = new RequestProfiler();
$response = $profiler->profileApiCall(
    fn() => $model->chat($messages),
    ['model' => 'gpt-3.5-turbo', 'messages_count' => count($messages)]
);
```

### 内存分析

监控 Odin 应用的内存使用：

```php
<?php

class MemoryProfiler
{
    private array $snapshots = [];
    
    public function takeSnapshot(string $label)
    {
        $this->snapshots[$label] = [
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true),
        ];
    }
    
    public function compareSnapshots(string $startLabel, string $endLabel): array
    {
        if (!isset($this->snapshots[$startLabel]) || !isset($this->snapshots[$endLabel])) {
            throw new \InvalidArgumentException('指定的快照标签不存在');
        }
        
        $start = $this->snapshots[$startLabel];
        $end = $this->snapshots[$endLabel];
        
        return [
            'memory_increase' => $end['memory'] - $start['memory'],
            'peak_increase' => $end['peak'] - $start['peak'],
            'duration' => $end['time'] - $start['time'],
        ];
    }
    
    public function generateReport(): string
    {
        // 生成详细的内存使用报告
        $report = "内存使用报告:\n";
        
        foreach ($this->snapshots as $label => $data) {
            $memory = number_format($data['memory'] / 1024 / 1024, 2);
            $peak = number_format($data['peak'] / 1024 / 1024, 2);
            
            $report .= "- {$label}: 当前内存 {$memory}MB, 峰值 {$peak}MB\n";
        }
        
        return $report;
    }
}

// 使用示例
$profiler = new MemoryProfiler();
$profiler->takeSnapshot('开始');

// 执行操作...
$agent->chat(new UserMessage('复杂请求'));

$profiler->takeSnapshot('结束');
echo $profiler->generateReport();
echo "差异: " . print_r($profiler->compareSnapshots('开始', '结束'), true);
```

### 性能优化工具

推荐使用的性能优化工具：

1. **Xdebug Profiler**：生成详细的函数调用分析
   ```bash
   # 安装 Xdebug
   pecl install xdebug
   
   # 在 php.ini 中配置
   xdebug.mode=profile
   xdebug.output_dir=/tmp/profiler
   ```

2. **Blackfire**：专业的 PHP 应用性能分析工具
   ```bash
   # 安装 Blackfire CLI
   curl -s https://packagecloud.io/gpg.key | sudo apt-key add -
   echo "deb http://packages.blackfire.io/debian any main" | sudo tee /etc/apt/sources.list.d/blackfire.list
   sudo apt-get update
   sudo apt-get install blackfire
   
   # 使用 Blackfire 分析脚本
   blackfire run php your_script.php
   ```

3. **编写性能基准测试**：测量关键操作的性能
   ```php
   <?php
   
   use Hyperf\Odin\Memory\MemoryManager;
   use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
   use Hyperf\Odin\Message\UserMessage;
   
   $iterations = 1000;
   $memory = new MemoryManager();
   $memory->setPolicy(new LimitCountPolicy(10));
   
   $start = microtime(true);
   
   for ($i = 0; $i < $iterations; $i++) {
       $memory->addMessage(new UserMessage("测试消息 {$i}"));
       $processed = $memory->getProcessedMessages();
   }
   
   $end = microtime(true);
   $duration = $end - $start;
   $avgTime = $duration / $iterations;
   
   echo "执行 {$iterations} 次记忆处理操作:\n";
   echo "总时间: {$duration} 秒\n";
   echo "平均时间: {$avgTime} 秒\n";
   ```

### 性能监控

长期监控 Odin 应用的性能：

1. **创建性能日志记录器**
   ```php
   <?php
   
   class PerformanceLogger
   {
       private $logFile;
       
       public function __construct(string $logFile = null)
       {
           $this->logFile = $logFile ?? BASE_PATH . '/runtime/logs/performance.log';
       }
       
       public function log(string $operation, float $duration, array $context = []): void
       {
           $entry = [
               'timestamp' => date('Y-m-d H:i:s'),
               'operation' => $operation,
               'duration' => $duration,
               'memory' => memory_get_usage(true),
               'context' => $context,
           ];
           
           file_put_contents(
               $this->logFile,
               json_encode($entry) . "\n",
               FILE_APPEND
           );
       }
   }
   ```

2. **集成到应用中**
   ```php
   $perfLogger = new PerformanceLogger();
   
   // 在模型调用前后记录性能
   $start = microtime(true);
   $response = $model->chat($messages);
   $duration = microtime(true) - $start;
   
   $perfLogger->log('model_chat', $duration, [
       'model' => $model->getModelName(),
       'tokens' => $response->usage->total_tokens ?? 0,
   ]);
   ```

3. **设置性能警报**
   ```php
   function checkPerformance(float $duration, float $threshold): void
   {
       if ($duration > $threshold) {
           // 发送警报
           error_log("性能警告: 操作耗时 {$duration} 秒，超过阈值 {$threshold} 秒");
           
           // 可以发送电子邮件或其他通知
       }
   }
   
   // 使用示例
   $start = microtime(true);
   $result = $agent->chat($userMessage);
   $duration = microtime(true) - $start;
   
   checkPerformance($duration, 5.0); // 如果操作超过 5 秒，发出警报
   ```

通过本文档介绍的测试方法、调试技术和性能优化工具，开发者可以确保使用 Odin 框架构建的应用既稳定可靠又高效运行。良好的测试和调试实践是开发高质量 LLM 应用的关键。 
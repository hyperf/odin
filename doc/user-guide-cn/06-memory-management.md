# 记忆管理

> 本章详细介绍 Odin 框架的记忆管理系统，包括记忆策略、记忆驱动以及实际应用场景，帮助您构建具有持久对话能力的 AI 应用。

## 记忆系统概述

记忆管理是 Odin 框架的核心组件之一，负责存储、检索和优化对话历史，确保大型语言模型（LLM）能够在有限的上下文窗口内获得最相关的信息，从而保持对话的连贯性和上下文理解能力。

### 记忆系统架构

Odin 的记忆系统由以下核心组件构成：

- **记忆管理器（MemoryManager）**：协调记忆驱动和策略的交互，管理整体记忆流程
- **记忆驱动（Driver）**：负责消息的存储和检索，支持多种存储后端
- **记忆策略（Policy）**：定义如何筛选和优化消息列表，适应不同场景需求
- **消息历史（MessageHistory）**：维护对话的时间线和状态

## 记忆策略

记忆策略定义了如何处理、筛选和优化对话历史，以确保模型能够在有限的上下文窗口内获得最相关的信息。

### 已实现的记忆策略

Odin 框架目前已实现以下记忆策略：

1. **令牌限制策略（TokenLimitPolicy）**：根据估算的令牌数量限制保留消息，确保不超过模型的上下文窗口限制。
   
2. **消息数量限制策略（LimitCountPolicy）**：根据消息数量限制保留最新的消息，同时保留第一条用户消息，并优先删除工具调用相关消息。

3. **复合策略（CompositePolicy）**：组合多个策略，按照优先级顺序应用，提供更灵活的记忆管理方式。

### 待实现的记忆策略

> 注意：以下策略在框架中已定义接口和基础结构，但核心逻辑尚未实现。您可以根据自己的需求进行扩展实现。

1. **相关性策略（RelevancyPolicy）**：根据消息与当前上下文的相关性，保留最相关的消息。需要集成向量数据库或嵌入模型进行相关性计算。

2. **摘要策略（SummarizationPolicy）**：将历史消息摘要为系统消息，减少上下文长度。需要利用 LLM 对消息进行摘要，生成系统消息。

3. **时间窗口策略（TimeWindowPolicy）**：根据消息时间戳，只保留特定时间窗口内的消息。

### 策略选择指南

根据不同场景选择合适的记忆策略：

| 策略类型 | 适用场景 | 优点 | 缺点 | 实现状态 |
|---------|----------|------|------|---------|
| 令牌限制 | 大多数生产环境 | 有效利用上下文窗口，适应不同模型限制 | 需要计算令牌，可能截断消息 | ✅ 已实现 |
| 消息数量限制 | 对话机器人，客服系统 | 实现简单，保留最新交互和初始上下文 | 无法根据内容重要性筛选 | ✅ 已实现 |
| 复合策略 | 复杂应用场景 | 灵活组合多种策略的优势 | 配置复杂，可能有策略冲突 | ✅ 已实现 |
| 相关性策略 | 专业领域对话，决策支持 | 保留关键信息 | 计算开销大，需要额外训练 | ❌ 待实现 |
| 摘要策略 | 长对话，多轮交互 | 大幅减少令牌使用 | 可能丢失细节，需要额外调用 | ❌ 待实现 |
| 时间窗口 | 时效性强的场景 | 关注最新信息 | 可能丢失历史重要信息 | ❌ 待实现 |

## 记忆管理器使用方法

`MemoryManager` 类是管理对话历史的核心组件，提供了添加、检索、过滤和应用策略等功能。

### 基本用法

```php
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;

// 创建记忆管理器
$memory = new MemoryManager();

// 设置记忆策略
$policy = new TokenLimitPolicy();
$policy->configure(['max_tokens' => 4000]); // 配置策略参数
$memory->setPolicy($policy);

// 添加系统消息（会添加到系统消息区域）
$memory->addSystemMessage(new SystemMessage('你是一个专业、友好的AI助手'));

// 添加用户消息
$memory->addMessage(new UserMessage('你好，Odin！'));

// 添加助手响应
$memory->addMessage(new AssistantMessage('你好！我是 Odin，有什么可以帮助你的？'));

// 获取所有消息（不包括系统消息，未应用策略）
$allMessages = $memory->getMessages();

// 获取系统消息
$systemMessages = $memory->getSystemMessages();

// 获取应用策略后的消息（包含系统消息）
$processedMessages = $memory->getProcessedMessages();

// 手动应用策略（通常不需要，getProcessedMessages会自动应用）
$memory->applyPolicy();

// 清空记忆
$memory->clear();
```

### 复合策略的使用

复合策略允许组合多个独立策略，按照优先级顺序应用：

```php
use Hyperf\Odin\Memory\Policy\CompositePolicy;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;

// 创建复合策略
$compositePolicy = new CompositePolicy();

// 添加多个策略（顺序很重要，会按照添加顺序应用）
$compositePolicy->addPolicy(new TokenLimitPolicy(['max_tokens' => 4000]));
$compositePolicy->addPolicy(new LimitCountPolicy(['max_count' => 20]));

// 设置复合策略
$memory->setPolicy($compositePolicy);
```

复合策略的处理逻辑：
1. 按照添加顺序依次应用每个策略
2. 每个策略处理上一个策略的输出
3. 最终结果是所有策略链式处理后的消息列表

### 消息数量限制策略的优化特性

`LimitCountPolicy` 包含一些特殊优化逻辑：

```php
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;

// 创建消息数量限制策略
$policy = new LimitCountPolicy([
    'max_count' => 10,                 // 最大消息数量
    'keep_first_user_message' => true, // 保留第一条用户消息（默认为true）
    'priority_removal' => ['tool']     // 优先删除的消息类型（工具消息）
]);

$memory->setPolicy($policy);
```

此策略的优化特性：
- 保留第一条用户消息，保持对话的上下文连贯性
- 优先删除工具调用相关消息，保留用户和助手的对话内容
- 在达到限制时，从中间开始删除消息，保留最新和最早的关键消息

## 记忆驱动

记忆驱动负责消息的实际存储和检索。Odin 框架目前支持内存驱动，并可以扩展支持其他类型的存储后端。

### 内存驱动（InMemoryDriver）

内存驱动是默认的记忆驱动，将消息存储在应用程序内存中。适用于无需持久化存储的短期对话场景。

```php
use Hyperf\Odin\Memory\Driver\InMemoryDriver;
use Hyperf\Odin\Memory\MemoryManager;

// 创建内存驱动（可选参数）
$driver = new InMemoryDriver([
    'max_messages' => 200, // 最大消息数量限制
]);

// 使用此驱动创建记忆管理器
$memory = new MemoryManager($driver);
```

### 自定义记忆驱动

通过实现 `DriverInterface` 接口，可以创建自定义的记忆驱动，例如 Redis 驱动或数据库驱动：

```php
<?php

namespace App\Memory\Driver;

use Hyperf\Odin\Contract\Memory\DriverInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;

class RedisDriver implements DriverInterface
{
    protected \Redis $redis;
    protected string $prefix;
    protected string $sessionId;
    
    public function __construct(\Redis $redis, string $sessionId, string $prefix = 'odin:memory:')
    {
        $this->redis = $redis;
        $this->sessionId = $sessionId;
        $this->prefix = $prefix;
    }
    
    public function addMessage(MessageInterface $message): void
    {
        $key = $this->prefix . $this->sessionId . ':messages';
        $this->redis->rPush($key, serialize($message));
    }
    
    public function addSystemMessage(MessageInterface $message): void
    {
        $key = $this->prefix . $this->sessionId . ':system';
        $this->redis->rPush($key, serialize($message));
    }
    
    public function getMessages(): array
    {
        $key = $this->prefix . $this->sessionId . ':messages';
        $serialized = $this->redis->lRange($key, 0, -1);
        
        return array_map(function ($item) {
            return unserialize($item);
        }, $serialized);
    }
    
    public function getSystemMessages(): array
    {
        $key = $this->prefix . $this->sessionId . ':system';
        $serialized = $this->redis->lRange($key, 0, -1);
        
        return array_map(function ($item) {
            return unserialize($item);
        }, $serialized);
    }
    
    public function clear(): void
    {
        $this->redis->del($this->prefix . $this->sessionId . ':messages');
        $this->redis->del($this->prefix . $this->sessionId . ':system');
    }
}
```

## 实际应用场景示例

### 简单聊天机器人

在标准聊天应用中使用令牌限制策略：

```php
use Hyperf\Odin\Model\ModelFactory;
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\AssistantMessage;

// 初始化
$model = ModelFactory::create(
    implementation: OpenAIModel::class,
    modelName: 'gpt-3.5-turbo',
    config: ['api_key' => $apiKey]
);

$memory = new MemoryManager();
$memory->setPolicy(new TokenLimitPolicy(['max_tokens' => 4000]));

// 添加系统指令
$memory->addSystemMessage(new SystemMessage('你是一个友好的中文助手，回答用户问题时使用礼貌的语气。'));

// 用户询问
$userMessage = new UserMessage('什么是人工智能？');
$memory->addMessage($userMessage);

// 获取模型响应
$response = $model->chat($memory->getProcessedMessages());
$assistantMessage = new AssistantMessage($response->choices[0]->message->content);
$memory->addMessage($assistantMessage);

// 用户跟进问题
$userMessage = new UserMessage('它有哪些应用领域？');
$memory->addMessage($userMessage);

// 再次获取响应（模型能够理解前后上下文）
$response = $model->chat($memory->getProcessedMessages());
```

### 多轮复杂对话

使用复合策略处理多轮复杂对话：

```php
use Hyperf\Odin\Memory\Policy\CompositePolicy;
use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;

// 创建复合策略
$policy = new CompositePolicy();
$policy->addPolicy(new TokenLimitPolicy(['max_tokens' => 4000]));
$policy->addPolicy(new LimitCountPolicy(['max_count' => 20, 'keep_first_user_message' => true]));

$memory = new MemoryManager();
$memory->setPolicy($policy);

// 添加系统消息
$memory->addSystemMessage(new SystemMessage('你是一个专业助手，擅长帮助用户解决复杂问题。'));

// 进行多轮对话...
for ($i = 0; $i < 30; $i++) {
    // 添加用户消息
    $memory->addMessage(new UserMessage("这是第 {$i} 个问题..."));
    
    // 获取模型响应
    $response = $model->chat($memory->getProcessedMessages());
    $memory->addMessage(new AssistantMessage($response->choices[0]->message->content));
}

// 最终会自动保留第一条用户消息和最近的消息，同时确保总令牌数不超过限制
```

## 自定义记忆策略实现

通过继承 `AbstractPolicy` 类或直接实现 `PolicyInterface` 接口创建自定义记忆策略：

```php
<?php

namespace App\Memory\Policy;

use Hyperf\Odin\Memory\Policy\AbstractPolicy;
use Hyperf\Odin\Message\AbstractMessage;

class CustomPolicy extends AbstractPolicy
{
    /**
     * 处理消息列表，返回经过策略处理后的消息列表
     */
    public function process(array $messages): array
    {
        // 获取配置参数（可通过configure方法设置）
        $maxMessages = $this->getOption('max_messages', 10);
        $importanceThreshold = $this->getOption('importance_threshold', 0.5);
        
        // 实现自定义过滤/排序/优化逻辑
        $filteredMessages = [];
        
        foreach ($messages as $message) {
            // 示例：实现自定义过滤逻辑
            if ($this->isImportantMessage($message)) {
                $filteredMessages[] = $message;
            }
        }
        
        // 确保不超过最大消息数
        if (count($filteredMessages) > $maxMessages) {
            $filteredMessages = array_slice($filteredMessages, -$maxMessages);
        }
        
        return $filteredMessages;
    }
    
    /**
     * 获取默认配置选项
     */
    protected function getDefaultOptions(): array
    {
        return [
            'max_messages' => 10,
            'importance_threshold' => 0.5,
        ];
    }
    
    /**
     * 判断消息是否重要
     */
    private function isImportantMessage(AbstractMessage $message): bool
    {
        // 实现消息重要性评估逻辑
        // 例如：基于关键词、长度、内容类型等
        return true;
    }
}
```

### 实现摘要策略示例

以下是一个简单的摘要策略实现示例：

```php
<?php

namespace App\Memory\Policy;

use Hyperf\Odin\Memory\Policy\AbstractPolicy;
use Hyperf\Odin\Message\AbstractMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Contract\Model\ModelInterface;

class SummaryPolicy extends AbstractPolicy
{
    private ModelInterface $model;
    private ?string $currentSummary = null;
    
    public function __construct(ModelInterface $model, array $options = [])
    {
        $this->model = $model;
        parent::__construct($options);
    }
    
    public function process(array $messages): array
    {
        $threshold = $this->getOption('threshold', 10);
        $keepRecent = $this->getOption('keep_recent', 5);
        
        // 如果消息数量小于阈值，不需要摘要
        if (count($messages) <= $threshold) {
            return $messages;
        }
        
        // 保留最近的消息
        $recentMessages = array_slice($messages, -$keepRecent);
        
        // 需要摘要的消息
        $messagesToSummarize = array_slice($messages, 0, count($messages) - $keepRecent);
        
        // 生成摘要
        $summary = $this->generateSummary($messagesToSummarize);
        $this->currentSummary = $summary;
        
        // 创建摘要系统消息
        $summaryMessage = new SystemMessage("对话摘要：{$summary}");
        
        // 返回摘要消息加上最近消息
        return array_merge([$summaryMessage], $recentMessages);
    }
    
    protected function getDefaultOptions(): array
    {
        return [
            'threshold' => 10,    // 触发摘要的消息数量阈值
            'keep_recent' => 5,   // 保留最近的消息数量
        ];
    }
    
    private function generateSummary(array $messages): string
    {
        // 构建摘要提示
        $prompt = "请总结以下对话内容，提取关键信息：\n\n";
        
        foreach ($messages as $message) {
            $role = $message->getRole();
            $content = $message->getContent();
            $prompt .= "{$role}: {$content}\n";
        }
        
        // 调用模型生成摘要
        $response = $this->model->completions($prompt, [
            'max_tokens' => 150,
            'temperature' => 0.7,
        ]);
        
        return trim($response->choices[0]->text);
    }
    
    public function getCurrentSummary(): ?string
    {
        return $this->currentSummary;
    }
}
```

## 结论

Odin 框架的记忆管理系统提供了灵活且强大的方式来管理对话历史，使 LLM 应用能够保持上下文连贯性和智能性。通过选择合适的记忆策略，您可以优化模型性能，同时降低 API 调用成本和提高用户体验。

随着应用复杂度的增加，您可以实现和组合更多自定义策略，或扩展现有策略，以满足特定业务需求。目前框架中的部分策略（如相关性策略、摘要策略和时间窗口策略）尚未完全实现，欢迎贡献您的实现或根据实际需求进行定制开发。 
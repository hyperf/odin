# 安装和配置

> 本章节将指导您完成 Odin 框架的安装和配置过程。

## 系统要求

在开始安装 Odin 之前，请确保您的系统满足以下要求：

### 必需组件
- PHP >= 8.0
- PHP 扩展：
  - bcmath
  - curl
  - mbstring
- Composer >= 2.0
- Hyperf 框架 (2.2.x, 3.0.x 或 3.1.x)

### 推荐配置
- PHP 8.1 或更高版本
- Swow/Swoole 扩展（用于异步处理）

## 安装步骤

### 1. 通过 Composer 安装

在您的 Hyperf 项目中使用 Composer 安装 Odin 包：

```bash
composer require hyperf/odin
```

### 2. 发布配置文件

安装完成后，需要发布 Odin 的配置文件到您的项目中：

```bash
php bin/hyperf.php vendor:publish hyperf/odin
```

这将在您的项目 `config/autoload` 目录下创建 `odin.php` 配置文件。

### 3. 安装依赖项

Odin 的核心依赖项已经包含在了包中，包括：

```bash
# 向量存储支持
hyperf/qdrant-client

# 用于计算 token 数量
yethee/tiktoken
```

如果您需要使用 AWS Bedrock 服务，需要额外安装 AWS SDK：

```bash
composer require aws/aws-sdk-php
```

## 初始配置引导

### 基本配置

安装完成后，首先需要配置您将使用的 LLM 提供商信息。Odin 支持多种 LLM 提供商，包括 OpenAI、Azure OpenAI、AWS Bedrock、Doubao、ChatGLM 等模型。

### 配置默认模型

在 `config/autoload/odin.php` 中设置默认模型：

```php
return [
    'llm' => [
        'default' => 'gpt-4o-global', // 设置您的默认模型
        // ... 其他配置
    ],
    // ... 其他配置
];
```

## 环境变量配置

Odin 使用环境变量来管理敏感信息，如 API 密钥。您需要在项目根目录的 `.env` 文件中配置这些变量。

### 常用环境变量

```dotenv
# OpenAI 配置
OPENAI_API_KEY=your_openai_api_key
OPENAI_BASE_URL=https://api.openai.com/v1  # 可选，默认为 OpenAI 官方 API
OPENAI_ORG_ID=your_organization_id         # 可选，组织 ID

# Azure OpenAI 配置
AZURE_OPENAI_API_KEY=your_azure_openai_api_key
AZURE_OPENAI_API_BASE=https://your-resource-name.openai.azure.com
AZURE_OPENAI_API_VERSION=2023-05-15
AZURE_OPENAI_DEPLOYMENT_NAME=your_deployment_name

# GPT-4o 配置示例 (使用Azure OpenAI)
AZURE_OPENAI_4O_API_KEY=your_azure_openai_4o_api_key
AZURE_OPENAI_4O_API_BASE=https://your-resource-name.openai.azure.com
AZURE_OPENAI_4O_API_VERSION=2023-07-01-preview
AZURE_OPENAI_4O_DEPLOYMENT_NAME=gpt-4o

# AWS Bedrock 配置
AWS_ACCESS_KEY_ID=your_aws_access_key_id
AWS_SECRET_ACCESS_KEY=your_aws_secret_access_key
AWS_REGION=us-east-1

# ChatGLM 配置
GLM_MODEL=THUDM/glm-edge-1.5b-chat

# Doubao 模型配置
DOUBAO_API_KEY=your_doubao_api_key
DOUBAO_BASE_URL=https://api.doubao.com
```

## 配置文件详解

Odin 的主要配置文件位于 `config/autoload/odin.php`，下面是各主要配置项的说明：

### LLM 模型配置

```php
'llm' => [
    'default' => 'gpt-4o-global', // 默认使用的模型
    'general_model_options' => [
        // 通用模型选项
        'chat' => true,               // 是否支持聊天功能
        'function_call' => false,     // 是否支持函数调用
        'embedding' => false,         // 是否支持嵌入
        'multi_modal' => false,       // 是否支持多模态输入
        'vector_size' => 0,           // 向量大小
    ],
    'general_api_options' => [
        // 通用 API 选项
        'timeout' => [
            'connection' => 5.0,      // 连接超时（秒）
            'write' => 10.0,          // 写入超时（秒）
            'read' => 300.0,          // 读取超时（秒）
            'total' => 350.0,         // 总体超时（秒）
            'thinking' => 120.0,      // 思考超时（秒）
            'stream_chunk' => 30.0,   // 流式块间超时（秒）
            'stream_first' => 60.0,   // 首个流式块超时（秒）
        ],
        'custom_error_mapping_rules' => [], // 自定义错误映射规则
    ],
    'models' => [
        // OpenAI 模型配置示例
        'gpt-3.5-turbo' => [
            'implementation' => OpenAIModel::class,
            'config' => [
                'api_key' => env('OPENAI_API_KEY'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
                'organization' => env('OPENAI_ORG_ID', ''),
            ],
            'model_options' => [
                'name' => 'gpt-3.5-turbo',
                'chat' => true,
                'function_call' => true,
                'embedding' => false,
                'multi_modal' => false,
                'vector_size' => 0,
            ],
            'api_options' => [
                'timeout' => [
                    'connection' => 5.0,
                    'write' => 10.0,
                    'read' => 120.0,
                    'total' => 150.0,
                    'thinking' => 60.0,
                    'stream_chunk' => 10.0,
                    'stream_first' => 30.0,
                ],
                'custom_error_mapping_rules' => [],
            ],
        ],
        
        // Azure OpenAI 模型配置示例
        'azure-gpt-4' => [
            'implementation' => AzureOpenAIModel::class,
            'config' => [
                'api_key' => env('AZURE_OPENAI_API_KEY'),
                'api_base' => env('AZURE_OPENAI_API_BASE'),
                'api_version' => env('AZURE_OPENAI_API_VERSION'),
                'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT_NAME'),
            ],
            'model_options' => [
                'chat' => true,
                'function_call' => true,
                'embedding' => false,
                'multi_modal' => false,
                'vector_size' => 0,
            ],
            'api_options' => [
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
            ],
        ],
        
        // AWS Bedrock 模型配置示例
        'claude-3-sonnet' => [
            'implementation' => AwsBedrockModel::class,
            'config' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_REGION', 'us-east-1'),
                'model_id' => 'anthropic.claude-3-sonnet-20240229-v1:0',
            ],
            'model_options' => [
                'chat' => true,
                'function_call' => true,
                'embedding' => false,
                'multi_modal' => true,
                'vector_size' => 0,
            ],
            'api_options' => [
                'timeout' => [
                    'connection' => 5.0,
                    'write' => 10.0,
                    'read' => 300.0,
                    'total' => 350.0,
                    'thinking' => 120.0,
                    'stream_chunk' => 30.0,
                    'stream_first' => 60.0,
                ],
            ],
        ],
    ],
],
```

## 验证安装

安装完成后，您可以通过以下代码验证安装是否成功：

```php
<?php

declare(strict_types=1);

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
require_once BASE_PATH . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\OpenAIModel;

// 初始化容器
ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建模型实例
$model = new OpenAIModel(
    'gpt-3.5-turbo',  // 模型名称
    [
        'api_key' => getenv('OPENAI_API_KEY'),
        'base_url' => getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1',
    ],
    new Logger(),
);

// 构建消息
$messages = [
    new SystemMessage('你是一个有用的AI助手。'),
    new UserMessage('你好，我是一个测试消息'),
];

// 发送请求
$response = $model->chat($messages);

// 输出响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getContent();
}
```

如果一切配置正确，您应该能看到模型的回复。

### 流式响应示例

如果您需要使用流式响应，可以使用以下代码：

```php
// ... 前面的代码相同 ...

// 发送流式请求
$response = $model->chatStream($messages);

// 处理流式响应
foreach ($response->getStreamIterator() as $choice) {
    $message = $choice->getMessage();
    if ($message instanceof AssistantMessage) {
        echo $message->getContent();
    }
}
```

## 故障排除

如果您在安装或配置过程中遇到问题，请检查：

1. PHP 版本是否符合要求
2. 所需的 PHP 扩展是否已安装
3. 环境变量和配置文件是否正确设置
4. API 密钥是否有效
5. 查看日志文件（通常位于`runtime/logs/`）

## 下一步

- 了解 [核心概念](./02-core-concepts.md)
- 探索 [API 参考](./03-api-reference.md)
- 学习如何 [配置不同模型提供商](./04-model-providers.md)

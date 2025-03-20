# 模型提供商

> 本章节详细介绍 Odin 框架支持的各种模型提供商，包括它们的特性、配置方法和使用示例。

## 支持的模型一览

Odin 框架支持多种大型语言模型提供商，您可以根据项目需求选择合适的模型。目前支持的主要模型包括：

| 模型类型         | 实现类                | 描述                                 |
| ------------ | ------------------ | ---------------------------------- |
| Azure OpenAI | `AzureOpenAIModel` | 微软 Azure OpenAI 服务，提供 OpenAI 的模型接口 |
| OpenAI       | `OpenAIModel`      | OpenAI 官方 API 接口                   |
| AWS Bedrock  | `AwsBedrockModel`  | 亚马逊 AWS Bedrock 服务，提供多种基础模型接口     |
| 豆包 AI        | `DoubaoModel`      | 字节跳动旗下的大语言模型服务                     |
| DeepSeek     | `DoubaoModel`      | 通过豆包 API 访问的 DeepSeek 模型            |
| ChatGLM      | `ChatglmModel`     | 清华大学开发的双语对话语言模型                    |
| RWKV         | `RWKVModel`        | 开源的 RNN-based 大语言模型                |
| Ollama       | `OllamaModel`      | 本地运行的开源模型运行时                       |

## 模型配置方法

### Azure OpenAI

Azure OpenAI 是微软提供的 OpenAI 模型托管服务，提供企业级的安全性和合规性。

#### 环境变量配置

```bash
# Azure OpenAI 环境变量
AZURE_OPENAI_API_KEY=your_api_key
AZURE_OPENAI_API_BASE=https://your-resource-name.openai.azure.com
AZURE_OPENAI_API_VERSION=2024-02-15
AZURE_OPENAI_DEPLOYMENT_NAME=your-deployment-name

# GPT-4o 模型配置
AZURE_OPENAI_4O_API_KEY=your_azure_openai_4o_api_key
AZURE_OPENAI_4O_API_BASE=https://your-resource-name.openai.azure.com
AZURE_OPENAI_4O_API_VERSION=2023-07-01-preview
AZURE_OPENAI_4O_DEPLOYMENT_NAME=gpt-4o
```

#### 代码示例

```php
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Logger;

// 创建 Azure OpenAI 模型实例
$model = new AzureOpenAIModel(
    'gpt-4o-global',  // 模型名称
    [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    new Logger(),
);
```

### OpenAI

OpenAI 提供原生的 API 接口，可以访问包括 GPT-4、GPT-3.5 等各种模型。

#### 环境变量配置

```bash
# OpenAI 环境变量
OPENAI_API_KEY=your_api_key
OPENAI_BASE_URL=https://api.openai.com/v1  # 可选，默认值
OPENAI_ORGANIZATION=your_organization_id   # 可选
```

#### 代码示例

```php
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\Logger;

// 创建 OpenAI 模型实例
$model = new OpenAIModel(
    'gpt-4o',  // 模型名称
    [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL'),  // 可选
        'organization' => env('OPENAI_ORGANIZATION'),  // 可选
    ],
    new Logger(),
);
```

### AWS Bedrock

AWS Bedrock 是亚马逊的托管服务，提供对多种基础模型的访问，包括 Anthropic 的 Claude 系列、Amazon Titan 和 Cohere 等。

#### 环境变量配置

```bash
# AWS Bedrock 环境变量
AWS_ACCESS_KEY=your_aws_access_key
AWS_SECRET_KEY=your_aws_secret_key
AWS_REGION=us-east-1  # 默认区域
AWS_CLAUDE_3_7_ENDPOINT=anthropic.claude-3-sonnet-20240229-v1:0  # Claude 模型端点ID
```

#### 代码示例

```php
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Logger;

// 创建 AWS Bedrock 模型实例（Claude 3.7）
$model = new AwsBedrockModel(
    env('AWS_CLAUDE_3_7_ENDPOINT'),  // 模型ID
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    new Logger(),
);
```

### 豆包 AI

豆包 AI 是字节跳动提供的大语言模型服务，对中文支持优化。

#### 环境变量配置

```bash
# 豆包 AI 环境变量
DOUBAO_API_KEY=your_api_key
DOUBAO_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
DOUBAO_PRO_32K_ENDPOINT=your_endpoint_name
DOUBAO_1_5_VISION_PRO_32K_ENDPOINT=your_vision_endpoint_name  # 多模态模型
```

#### 代码示例

```php
use Hyperf\Odin\Model\DoubaoModel;
use Hyperf\Odin\Logger;

// 创建豆包 AI 模型实例
$model = new DoubaoModel(
    env('DOUBAO_PRO_32K_ENDPOINT'),  // 端点名称
    [
        'api_key' => env('DOUBAO_API_KEY'),
        'base_url' => env('DOUBAO_BASE_URL'),
    ],
    new Logger(),
);

// 创建多模态模型实例
$visionModel = new DoubaoModel(
    env('DOUBAO_1_5_VISION_PRO_32K_ENDPOINT'),  // 多模态端点
    [
        'api_key' => env('DOUBAO_API_KEY'),
        'base_url' => env('DOUBAO_BASE_URL'),
    ],
    new Logger(),
);
```

### DeepSeek

DeepSeek 模型通过豆包 API 提供访问，包括 DeepSeek-R1 和 DeepSeek-V3 等。

#### 环境变量配置

```bash
# DeepSeek 环境变量 (通过豆包 API)
DOUBAO_API_KEY=your_api_key
DOUBAO_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
DEEPSPEEK_R1_ENDPOINT=your_deepseek_r1_endpoint
DEEPSPEEK_V3_ENDPOINT=your_deepseek_v3_endpoint
```

#### 代码示例

```php
use Hyperf\Odin\Model\DoubaoModel;
use Hyperf\Odin\Logger;

// 创建 DeepSeek-R1 模型实例
$model = new DoubaoModel(
    env('DEEPSPEEK_R1_ENDPOINT'),  // DeepSeek-R1 端点
    [
        'api_key' => env('DOUBAO_API_KEY'),
        'base_url' => env('DOUBAO_BASE_URL'),
    ],
    new Logger(),
);
```

### ChatGLM

ChatGLM 是清华大学开发的对话语言模型，支持本地部署。

#### 环境变量配置

```bash
# ChatGLM 环境变量
GLM_MODEL=THUDM/glm-edge-1.5b-chat
MISC_API_KEY=your_api_key  # 如果通过API访问
MISC_BASE_URL=your_api_base_url  # API地址
```

#### 代码示例

```php
use Hyperf\Odin\Model\ChatglmModel;
use Hyperf\Odin\Logger;

// 创建 ChatGLM 模型实例
$model = new ChatglmModel(
    env('GLM_MODEL'),  // 模型名称
    [
        'api_key' => env('MISC_API_KEY'),
        'base_url' => env('MISC_BASE_URL'),
    ],
    new Logger(),
);
```

### RWKV

RWKV 是一种基于 RNN 架构的开源大语言模型，可以本地部署。

#### 代码示例

```php
use Hyperf\Odin\Model\RWKVModel;
use Hyperf\Odin\Logger;

// 创建 RWKV 模型实例
$model = new RWKVModel(
    'rwkv-4-14b',  // 模型名称
    [
        'api_base' => 'http://localhost:8000',  // API 地址
    ],
    new Logger(),
);
```

### Ollama

Ollama 是一个本地运行开源模型的工具，支持多种开源模型。

#### 代码示例

```php
use Hyperf\Odin\Model\OllamaModel;
use Hyperf\Odin\Logger;

// 创建 Ollama 模型实例
$model = new OllamaModel(
    'llama3',  // 模型名称
    [
        'api_base' => 'http://localhost:11434',  // Ollama API 地址
    ],
    new Logger(),
);
```

## 配置文件中的模型设置

在 `config/autoload/odin.php` 配置文件中，您可以预定义多个模型配置，便于在应用中灵活切换：

```php
return [
    'llm' => [
        'default' => 'gpt-4o-global',  // 默认模型
        'models' => [
            'gpt-4o-global' => [
                'implementation' => AzureOpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
                    'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => true,
                    'embedding' => false,
                    'multi_modal' => true,
                    'vector_size' => 0,
                ],
            ],
            'claude-3.7' => [
                'implementation' => AwsBedrockModel::class,
                'model' => env('AWS_CLAUDE_3_7_ENDPOINT'),
                'config' => [
                    'access_key' => env('AWS_ACCESS_KEY'),
                    'secret_key' => env('AWS_SECRET_KEY'),
                    'region' => env('AWS_REGION', 'us-east-1'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => true,
                    'embedding' => false,
                    'multi_modal' => true,
                    'vector_size' => 0,
                ],
            ],
            'embedding-model' => [
                'implementation' => OpenAIModel::class,
                'config' => [
                    'api_key' => env('OPENAI_API_KEY'),
                    'base_url' => env('OPENAI_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => false,
                    'function_call' => false,
                    'embedding' => true,
                    'multi_modal' => false,
                    'vector_size' => 1536,
                ],
            ],
            // 更多模型配置...
        ],
    ],
];
```

## 添加新的模型提供商

Odin 框架设计为可扩展的，您可以轻松添加新的模型提供商支持。下面是添加新模型提供商的步骤：

### 1. 创建模型类

创建一个继承自 `AbstractModel` 的新类：

```php
<?php

namespace App\Model;

use Hyperf\Odin\Model\AbstractModel;
use Hyperf\Odin\Contract\Api\ClientInterface;

class CustomModel extends AbstractModel
{
    /**
     * 获取API客户端实例
     */
    protected function getClient(): ClientInterface
    {
        // 实现与自定义模型提供商的通信逻辑
        return new CustomClient(
            $this->config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }
    
    /**
     * 获取API版本路径（如有必要）
     */
    protected function getApiVersionPath(): string
    {
        return 'v1'; // 或返回空字符串
    }
}
```

### 2. 创建 API 客户端

如果需要，创建一个实现 `ClientInterface` 的客户端类：

```php
<?php

namespace App\Api;

use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Api\Providers\AbstractClient;

class CustomClient extends AbstractClient implements ClientInterface
{
    // 实现必要的方法...
}
```

### 3. 在配置文件中注册

在 `config/autoload/odin.php` 配置文件中注册您的自定义模型：

```php
return [
    'llm' => [
        'models' => [
            'custom-model' => [
                'implementation' => \App\Model\CustomModel::class,
                'config' => [
                    // 自定义配置参数
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => true,
                    'embedding' => false,
                    'multi_modal' => false,
                    'vector_size' => 0,
                ],
            ],
        ],
    ],
];
```

## 模型使用最佳实践

### 选择合适的模型

- 对于简单问答任务，使用较小模型如 GPT-3.5 以降低成本
- 对于复杂推理和创意任务，使用强大模型如 GPT-4o 或 Claude 3.7
- 对于多模态任务（处理图像和文本），使用 GPT-4o 或豆包视觉模型
- 对于本地部署或离线场景，使用 ChatGLM 或 Ollama
- 对于中文优化，可以选择豆包 AI 或 ChatGLM

### 模型性能优化

- 使用异步和流式响应模式处理长文本生成
- 为不同任务类型配置适当的超时参数
- 针对高并发场景，合理设置连接数和重试策略

## 下一步

了解了各种模型提供商后，您可以：

- 学习[工具开发](./05-tool-development.md)章节，了解如何扩展模型的能力
- 深入[记忆管理](./06-memory-management.md)章节，掌握对话上下文管理技术
- 探索[向量存储](./07-vector-storage.md)章节，构建知识检索系统

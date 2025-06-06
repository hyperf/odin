# Odin

Odin 是一个基于 PHP 的 LLM 应用开发框架，其命名灵感来自于北欧神话中的主神 Odin（奥丁）和他的两只乌鸦 Huginn 和 Muninn，Huginn 和 Muninn 分别代表的 **思想** 和 **记忆**，它们两个每天早上一破晓就飞到人间，到了晚上再将所见所闻带回给 Odin。    
此项目旨在帮助开发人员利用 LLM 技术创建更加智能和灵活的应用程序，通过提供一系列强大而易用的功能，为 LLM 技术落地提供了更多的可能性。

## 核心特性

- **多模型支持**：支持 OpenAI、Azure OpenAI、AWS Bedrock、Doubao、ChatGLM 等多种大语言模型
- **统一接口**：提供一致的 API 接口，简化与不同 LLM 提供商的集成
- **工具调用**：支持 Function Calling，允许模型调用自定义工具和函数
- **MCP 集成**：基于 [dtyq/php-mcp](https://github.com/dtyq/php-mcp) 实现 Model Context Protocol 支持，轻松接入外部工具和服务
- **记忆管理**：提供灵活的记忆管理系统，支持会话上下文保持
- **向量存储**：集成 Qdrant 向量数据库，支持知识检索和语义搜索
- **Agent 开发**：内置 Agent 框架，支持智能代理开发
- **高性能**：优化的实现，支持流式响应和高效处理

## 系统要求

- PHP >= 8.0
- PHP 扩展：bcmath、curl、mbstring
- Composer >= 2.0
- Hyperf 框架 (2.2.x, 3.0.x 或 3.1.x)

## 安装

```bash
composer require hyperf/odin
```

## 快速开始

1. 安装完成后，发布配置文件：

```bash
php bin/hyperf.php vendor:publish hyperf/odin
```

2. 在 `.env` 文件中配置你的 API 密钥：

```
OPENAI_API_KEY=your_openai_api_key
```

3. 在 `config/autoload/odin.php` 中设置默认模型：

```php
return [
    'llm' => [
        'default' => 'gpt-4o', // 设置你的默认模型
        // ... 其他配置
    ],
];
```

## 文档

详细的文档可在 `doc/user-guide` 目录中找到，包括：
- 安装和配置
- 核心概念
- API 参考
- 模型提供商
- 工具开发
- 记忆管理
- Agent 开发
- 示例项目
- MCP 集成
- 常见问题解答

## License

Odin is open-sourced software licensed under the [MIT license](https://github.com/hyperf/odin/blob/master/LICENSE).

# 简介

> 本文档介绍了 Odin 框架的基本概念、设计理念和核心价值。

## 什么是 Odin

Odin 是一个基于 PHP 的 LLM 应用开发框架，其命名灵感来自于北欧神话中的主神 Odin（奥丁）和他的两只乌鸦 Huginn 和 Muninn。Huginn 和 Muninn 分别代表的**思想**和**记忆**，它们每天早上一破晓就飞到人间，到了晚上再将所见所闻带回给 Odin。

此项目旨在帮助开发人员利用 LLM 技术创建更加智能和灵活的应用程序，通过提供一系列强大而易用的功能，为 LLM 技术落地提供了更多的可能性。项目提供一系列便捷的工具和API，简化与各种LLM提供商（如OpenAI、Azure OpenAI、AWS Bedrock等）的集成过程。

## 设计理念

Odin 的设计遵循以下核心理念：

- **简单易用**：提供简洁直观的API，降低开发人员的学习成本
- **高度灵活**：支持多种LLM提供商和向量数据库，适应不同场景需求
- **可扩展性**：模块化设计，便于扩展和定制
- **高性能**：优化的实现，支持流式响应和高效处理
- **标准规范**：遵循PSR规范，保持代码质量和可维护性

## 框架架构

Odin 框架的总体架构如下：

```
Odin
├── Api                 // 模型提供商API接口
│   ├── Providers
│   │   ├── OpenAI
│   │   ├── AzureOpenAI
│   │   └── AwsBedrock
│   ├── Request         // 请求相关
│   ├── RequestOptions  // 请求选项
│   ├── Response        // 响应处理
│   └── Transport       // 传输层
├── Model               // 模型实现
│   ├── OpenAIModel
│   ├── AzureOpenAIModel
│   ├── AwsBedrockModel
│   ├── OllamaModel
│   ├── ChatglmModel
│   ├── DoubaoModel
│   ├── RWKVModel
│   └── ...
├── Message             // 消息处理
├── Memory              // 记忆管理
├── Tool                // 工具调用
│   └── Definition      // 工具定义
├── Document            // 文档处理
├── VectorStore         // 向量存储
│   └── Qdrant          // Qdrant向量数据库支持
├── TextSplitter        // 文本分割
├── Loader              // 文档加载器
├── Knowledge           // 知识库管理
├── Prompt              // 提示词模板
├── Agent               // 智能代理
│   └── Tool            // 代理工具
├── Wrapper             // 外部服务包装器
├── Factory             // 工厂类
├── Utils               // 工具类
└── Contract            // 接口契约
```

## 核心概念和术语

- **LLM (Large Language Model)**：大型语言模型，如GPT、DeepSeek、Claude等
- **Provider**：模型提供商，如OpenAI、Azure OpenAI、AWS Bedrock等
- **Model**：模型实现，包括OpenAI、Azure OpenAI、AWS Bedrock、Ollama等多种模型支持
- **Tool**：工具，可以被LLM调用的函数
- **Memory**：记忆，用于存储和检索会话上下文
- **Embedding**：嵌入，文本的向量表示
- **Vector Store**：向量数据库，用于存储和检索向量，如Qdrant
- **Knowledge**：知识库，用于管理和检索知识
- **Prompt**：提示词，用于引导模型生成内容
- **Agent**：代理，能够规划和执行任务的智能体
- **RAG (Retrieval Augmented Generation)**：检索增强生成，通过检索相关信息来增强生成能力
- **Wrapper**：外部服务包装器，用于简化与外部服务的集成，如Tavily搜索API

## 下一步

- 查看[安装和配置](./01-installation.md)指南开始使用 Odin
- 了解[核心概念](./02-core-concepts.md)深入理解框架设计
- 浏览[示例项目](./11-examples.md)学习实际应用案例

# Introduction

> This document introduces the basic concepts, design philosophy, and core values of the Odin framework.

## What is Odin

Odin is a PHP-based LLM application development framework. Its naming is inspired by the chief god Odin from Norse mythology and his two ravens Huginn and Muninn. Huginn and Muninn represent **thought** and **memory** respectively. Every morning at dawn, they fly to the human world and return in the evening to bring back what they have seen and heard to Odin.

This project aims to help developers create more intelligent and flexible applications using LLM technology, providing more possibilities for LLM technology implementation through a series of powerful and easy-to-use features. The project provides a series of convenient tools and APIs to simplify the integration process with various LLM providers (such as OpenAI, Azure OpenAI, AWS Bedrock, etc.).

## Design Philosophy

Odin's design follows these core principles:

- **Simple and Easy to Use**: Provides clean and intuitive APIs, reducing the learning curve for developers
- **Highly Flexible**: Supports multiple LLM providers and vector databases, adapting to different scenario requirements
- **Extensible**: Modular design, easy to extend and customize
- **High Performance**: Optimized implementation, supporting streaming responses and efficient processing
- **Standard Compliance**: Follows PSR standards, maintaining code quality and maintainability

## Framework Architecture

The overall architecture of the Odin framework is as follows:

```
Odin
├── Api                 // Model provider API interfaces
│   ├── Providers
│   │   ├── OpenAI
│   │   ├── AzureOpenAI
│   │   └── AwsBedrock
│   ├── Request         // Request related
│   ├── RequestOptions  // Request options
│   ├── Response        // Response handling
│   └── Transport       // Transport layer
├── Model               // Model implementations
│   ├── OpenAIModel
│   ├── AzureOpenAIModel
│   ├── AwsBedrockModel
│   ├── OllamaModel
│   ├── ChatglmModel
│   ├── DoubaoModel
│   ├── RWKVModel
│   └── ...
├── Message             // Message handling
├── Memory              // Memory management
├── Tool                // Tool calling
│   └── Definition      // Tool definition
├── Document            // Document processing
├── VectorStore         // Vector storage
│   └── Qdrant          // Qdrant vector database support
├── TextSplitter        // Text splitting
├── Loader              // Document loader
├── Knowledge           // Knowledge base management
├── Prompt              // Prompt templates
├── Agent               // Intelligent agents
│   └── Tool            // Agent tools
├── Wrapper             // External service wrappers
├── Factory             // Factory classes
├── Utils               // Utility classes
└── Contract            // Interface contracts
```

## Core Concepts and Terminology

- **LLM (Large Language Model)**: Large language models such as GPT, DeepSeek, Claude, etc.
- **Provider**: Model providers such as OpenAI, Azure OpenAI, AWS Bedrock, etc.
- **Model**: Model implementations, including support for OpenAI, Azure OpenAI, AWS Bedrock, Ollama, and other models
- **Tool**: Tools that can be called by LLMs
- **Memory**: Memory for storing and retrieving conversation context
- **Embedding**: Vector representation of text
- **Vector Store**: Vector database for storing and retrieving vectors, such as Qdrant
- **Knowledge**: Knowledge base for managing and retrieving knowledge
- **Prompt**: Prompts used to guide model content generation
- **Agent**: Intelligent agents capable of planning and executing tasks
- **RAG (Retrieval Augmented Generation)**: Retrieval-augmented generation, enhancing generation capabilities by retrieving relevant information
- **Wrapper**: External service wrappers for simplifying integration with external services, such as Tavily Search API

## Next Steps

- View the [Installation and Configuration](./01-installation.md) guide to start using Odin
- Learn about [Core Concepts](./02-core-concepts.md) to understand the framework design in depth
- Browse [Example Projects](./09-examples.md) to learn practical use cases
English | [中文](README-CN.md)

# Odin

Odin is a PHP-based LLM application development framework. Its naming is inspired by the chief god Odin from Norse mythology and his two ravens Huginn and Muninn, which represent **thought** and **memory** respectively. Every morning at dawn, they fly to the human world and return in the evening to bring back what they have seen and heard to Odin.
This project aims to help developers create more intelligent and flexible applications using LLM technology, providing more possibilities for LLM technology implementation through a series of powerful and easy-to-use features.

## Core Features

- **Multi-Model Support**: Supports various large language models including OpenAI, Azure OpenAI, AWS Bedrock, Doubao, ChatGLM, and more
- **Unified Interface**: Provides consistent API interface, simplifying integration with different LLM providers
- **Tool Calling**: Supports Function Calling, allowing models to invoke custom tools and functions
- **MCP Integration**: Based on [dtyq/php-mcp](https://github.com/dtyq/php-mcp) to implement Model Context Protocol support, easily integrating external tools and services
- **Memory Management**: Provides flexible memory management system, supporting conversation context persistence
- **Vector Storage**: Integrates Qdrant vector database, supporting knowledge retrieval and semantic search
- **Agent Development**: Built-in Agent framework, supporting intelligent agent development
- **High Performance**: Optimized implementation, supporting streaming responses and efficient processing

## System Requirements

- PHP >= 8.0
- PHP Extensions: bcmath, curl, mbstring
- Composer >= 2.0
- Hyperf Framework (2.2.x, 3.0.x or 3.1.x)

## Installation

```bash
composer require hyperf/odin
```

## Quick Start

1. After installation, publish the configuration files:

```bash
php bin/hyperf.php vendor:publish hyperf/odin
```

2. Configure your API key in the `.env` file:

```
OPENAI_API_KEY=your_openai_api_key
```

3. Set the default model in `config/autoload/odin.php`:

```php
return [
    'llm' => [
        'default' => 'gpt-4o', // Set your default model
        // ... other configurations
    ],
];
```

## Documentation

Detailed documentation can be found in the `doc/user-guide` directory:
- [Installation and Configuration](doc/user-guide/01-installation.md)
- [Core Concepts](doc/user-guide/02-core-concepts.md)
- [API Reference](doc/user-guide/03-api-reference.md)
- [Model Providers](doc/user-guide/04-model-providers.md)
- [Tool Development](doc/user-guide/05-tool-development.md)
- [Memory Management](doc/user-guide/06-memory-management.md)
- [Agent Development](doc/user-guide/07-agent-development.md)
- [Example Projects](doc/user-guide/09-examples.md)
- [MCP Integration](doc/user-guide/11-mcp-integration.md)
- [Frequently Asked Questions](doc/user-guide/10-faq.md)

## License

Odin is open-sourced software licensed under the [MIT license](https://github.com/hyperf/odin/blob/master/LICENSE).
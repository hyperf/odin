<?php

use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\OllamaModel;
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\Model\SkylarkModel;
use function Hyperf\Support\env;

return [
    'llm' => [
        'default' => 'gpt-4',
        // Modify this according to your needs
        'models' => [
            'gpt-35-turbo' => [
                'implementation' => OpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_35_TURBO_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_35_TURBO_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_35_TURBO_API_VERSION', '2023-08-01-preview'),
                    'deployment_name' => env('AZURE_OPENAI_35_TURBO_DEPLOYMENT_NAME'),
                ],
            ],
            'gpt-3.5-turbo' => [
                'implementation' => AzureOpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_35_TURBO_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_35_TURBO_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_35_TURBO_API_VERSION', '2023-08-01-preview'),
                    'deployment_name' => env('AZURE_OPENAI_35_TURBO_DEPLOYMENT_NAME'),
                ],
            ],
            'gpt-3.5-turbo-16k' => [
                'implementation' => AzureOpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_35_TURBO_16K_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_35_TURBO_16K_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_35_TURBO_16K_API_VERSION', '2023-08-01-preview'),
                    'deployment_name' => env('AZURE_OPENAI_35_TURBO_16K_DEPLOYMENT_NAME'),
                ],
            ],
            'gpt-4-turbo' => [
                'implementation' => AzureOpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_4_TURBO_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_4_TURBO_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_4_TURBO_API_VERSION', '2023-08-01-preview'),
                    'deployment_name' => env('AZURE_OPENAI_4_TURBO_DEPLOYMENT_NAME'),
                ],
            ],
            'command-r:35b' => [
                'implementation' => OllamaModel::class,
                'config' => [
                    'base_url' => env('OLLAMA_BASE_URL'),
                ],
            ],
            'huozi3:latest' => [
                'implementation' => OllamaModel::class,
                'config' => [
                    'base_url' => env('OLLAMA_BASE_URL'),
                ],
            ],
            'qwen:14b-chat' => [
                'implementation' => OllamaModel::class,
                'config' => [
                    'base_url' => env('OLLAMA_BASE_URL'),
                ],
            ],
            'qwen:72b-chat' => [
                'implementation' => OllamaModel::class,
                'config' => [
                    'base_url' => env('OLLAMA_BASE_URL'),
                ],
            ],
            'yi:34b' => [
                'implementation' => OllamaModel::class,
                'config' => [
                    'base_url' => env('OLLAMA_BASE_URL'),
                ],
            ],
            'skylark:character-4k' => [
                'implementation' => SkylarkModel::class,
                'config' => [
                    'host' => env('SKYLARK_PRO_CHARACTER_4K_HOST', env('SKYLARK_PRO_HOST')),
                    'ak' => env('SKYLARK_PRO_CHARACTER_4K_AK', env('SKYLARK_PRO_AK')),
                    'sk' => env('SKYLARK_PRO_CHARACTER_4K_SK', env('SKYLARK_PRO_SK')),
                    'endpoint' => env('SKYLARK_PRO_CHARACTER_4K_ENDPOINT'),
                    'region' => env('SKYLARK_PRO_CHARACTER_4K_REGION', env('SKYLARK_PRO_REGION', 'cn-beijing')),
                    'service' => env('SKYLARK_PRO_CHARACTER_4K_SERVICE', env('SKYLARK_PRO_SERVICE', 'ml_maas')),
                ],
            ],
            'skylark:turbo-8k' => [
                'implementation' => SkylarkModel::class,
                'config' => [
                    'host' => env('SKYLARK_PRO_TURBO_8K_HOST', env('SKYLARK_PRO_HOST')),
                    'ak' => env('SKYLARK_PRO_TURBO_8K_AK', env('SKYLARK_PRO_AK')),
                    'sk' => env('SKYLARK_PRO_TURBO_8K_SK', env('SKYLARK_PRO_SK')),
                    'endpoint' => env('SKYLARK_PRO_TURBO_8K_ENDPOINT'),
                    'region' => env('SKYLARK_PRO_TURBO_8K_REGION', env('SKYLARK_PRO_REGION', 'cn-beijing')),
                    'service' => env('SKYLARK_PRO_TURBO_8K_SERVICE', env('SKYLARK_PRO_SERVICE', 'ml_maas')),
                ],
            ],
            'skylark:32k' => [
                'implementation' => SkylarkModel::class,
                'config' => [
                    'host' => env('SKYLARK_PRO_32K_HOST', env('SKYLARK_PRO_HOST')),
                    'ak' => env('SKYLARK_PRO_32K_AK', env('SKYLARK_PRO_AK')),
                    'sk' => env('SKYLARK_PRO_32K_SK', env('SKYLARK_PRO_SK')),
                    'endpoint' => env('SKYLARK_PRO_32K_ENDPOINT'),
                    'region' => env('SKYLARK_PRO_32K_REGION', env('SKYLARK_PRO_REGION', 'cn-beijing')),
                    'service' => env('SKYLARK_PRO_32K_SERVICE', env('SKYLARK_PRO_SERVICE', 'ml_maas')),
                ],
            ],
            'skylark:4k' => [
                'implementation' => SkylarkModel::class,
                'config' => [
                    'host' => env('SKYLARK_PRO_4K_HOST', env('SKYLARK_PRO_HOST')),
                    'ak' => env('SKYLARK_PRO_4K_AK', env('SKYLARK_PRO_AK')),
                    'sk' => env('SKYLARK_PRO_4K_SK', env('SKYLARK_PRO_SK')),
                    'endpoint' => env('SKYLARK_PRO_4K_ENDPOINT'),
                    'region' => env('SKYLARK_PRO_4K_REGION', env('SKYLARK_PRO_REGION', 'cn-beijing')),
                    'service' => env('SKYLARK_PRO_4K_SERVICE', env('SKYLARK_PRO_SERVICE', 'ml_maas')),
                ],
            ],
            'skylark:lite-8k' => [
                'implementation' => SkylarkModel::class,
                'config' => [
                    'host' => env('SKYLARK_PRO_LITE_8K_HOST', env('SKYLARK_PRO_HOST')),
                    'ak' => env('SKYLARK_PRO_LITE_8K_AK', env('SKYLARK_PRO_AK')),
                    'sk' => env('SKYLARK_PRO_LITE_8K_SK', env('SKYLARK_PRO_SK')),
                    'endpoint' => env('SKYLARK_PRO_LITE_8K_ENDPOINT'),
                    'region' => env('SKYLARK_PRO_LITE_8K_REGION', env('SKYLARK_PRO_REGION', 'cn-beijing')),
                    'service' => env('SKYLARK_PRO_LITE_8K_SERVICE', env('SKYLARK_PRO_SERVICE', 'ml_maas')),
                ],
            ],
        ],
    ],
    'azure' => [
        'text-embedding-ada-002' => [
            'api_key' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_KEY'),
            'api_base' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_BASE'),
            'api_version' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_DEPLOYMENT_NAME'),
        ],
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
    'tavily' => [
        'api_key' => env('TAVILY_API_KEY'),
    ],
];
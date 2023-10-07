<?php

use function Hyperf\Support\env;

return [
    'llm' => [
        'default' => 'gpt-3.5-turbo',
        // Modify this according to your needs
        'models' => [
            'gpt-3.5-turbo' => [
                'name' => 'gpt-3.5-turbo',
                'api_type' => 'azure',
            ],
            'gpt-3.5-turbo-16k' => [
                'name' => 'gpt-3.5-turbo-16k',
                'api_type' => 'azure',
            ],
            'gpt-4' => [
                'name' => 'gpt-4',
                'api_type' => 'azure',
            ],
            'gpt-4-32k' => [
                'name' => 'gpt-4-32k',
                'api_type' => 'azure',
            ],
        ],
    ],
    'azure' => [
        'gpt-3.5-turbo' => [
            'api_key' => env('AZURE_OPENAI_35_TURBO_API_KEY'),
            'api_base' => env('AZURE_OPENAI_35_TURBO_API_BASE'),
            'api_version' => env('AZURE_OPENAI_35_TURBO_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_35_TURBO_DEPLOYMENT_NAME'),
        ],
        'gpt-3.5-turbo-16k' => [
            'api_key' => env('AZURE_OPENAI_35_TURBO_16K_API_KEY'),
            'api_base' => env('AZURE_OPENAI_35_TURBO_16K_API_BASE'),
            'api_version' => env('AZURE_OPENAI_35_TURBO_16K_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_35_TURBO_16K_DEPLOYMENT_NAME'),
        ],
        'gpt-4' => [
            'api_key' => env('AZURE_OPENAI_4_API_KEY'),
            'api_base' => env('AZURE_OPENAI_4_API_BASE'),
            'api_version' => env('AZURE_OPENAI_4_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_4_DEPLOYMENT_NAME'),
        ],
        'gpt-4-32k' => [
            'api_key' => env('AZURE_OPENAI_4_32K_API_KEY'),
            'api_base' => env('AZURE_OPENAI_4_32K_API_BASE'),
            'api_version' => env('AZURE_OPENAI_4_32K_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_4_32K_DEPLOYMENT_NAME'),
        ],
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
];
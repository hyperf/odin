<?php

use function Hyperf\Support\env;

return [
    'llm' => [
        'default_api' => env('ODIN_LLM_DEFAULT_API', 'openai'),
        'default_model' => env('ODIN_LLM_DEFAULT_MODEL', 'gpt-3.5-turbo'),
    ],
    'azure' => [
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'api_base' => env('AZURE_OPENAI_API_BASE'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2023-08-01-preview'),
        'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT_NAME'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
];
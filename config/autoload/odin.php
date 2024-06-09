<?php

declare(strict_types=1);

/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ChatglmModel;
use Hyperf\Odin\Model\OllamaModel;
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\Model\SkylarkModel;
use function Hyperf\Support\env;
use function Hyperf\Support\value;

$ollamaModels = value(function () {
    $models = [];
    $names = [
        'codeqwen:7b',
        'command-r:35b',
        'command-r-plus:104b',
        'gemma:2b',
        'gemma:7b',
        'huozi3:latest',
        'llama3:70b',
        'llama3:8b',
        'llama3:instruct',
        'llava:34b',
        'mixtral:8x22b',
        'mixtral:8x7b',
        'mixtral:instruct',
        'mxbai-embed-large:latest',
        'neural-chat:7b',
        'qwen:14b',
        'qwen:32b',
        'qwen:4b',
        'qwen:72b',
        'qwen:7b',
        'qwen:110b',
        'snowflake-arctic-embed:335m',
        'wizardlm2:7b',
        'wizardlm2:8x22b',
        'yi:2x34b',
        'yi:34b',
    ];
    foreach ($names as $name) {
        $models[$name] = [
            'implementation' => OllamaModel::class,
            'config' => [
                'base_url' => env('OLLAMA_BASE_URL'),
            ],
        ];
    }
    return $models;
});

$glmModels = value(function () {
    $models = [];
    $names = [
        'glm-4-0520',
        'glm-4',
        'glm-4-air',
        'glm-4-airx',
        'glm-4-flash',
        'charglm-3'
    ];
    foreach ($names as $name) {
        $prefix = strtoupper(str_replace('-', '_', $name));
        $models[$name] = [
            'implementation' => ChatglmModel::class,
            'config' => [
                'api_key' => env($prefix . '_API_KEY', env('CHATGLM_API_KEY')),
                'base_url' => 'https://open.bigmodel.cn',
            ],
        ];
    }
    return $models;
});

$models = [
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
    'text-embedding-ada-002' => [
        'implementation' => AzureOpenAIModel::class,
        'config' => [
            'api_key' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_KEY'),
            'api_base' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_BASE'),
            'api_version' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_API_VERSION', '2023-08-01-preview'),
            'deployment_name' => env('AZURE_OPENAI_TEXT_EMBEDDING_ADA_002_DEPLOYMENT_NAME'),
        ],
    ],
    'glm-4-9b' => [
        'implementation' => OpenAIModel::class,
        'config' => [
            'api_key' => env('GLM_4_9B_API_KEY'),
            'base_url' => env('GLM_4_9B_BASE_URL'),
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
    'moonshot-v1-8k' => [
        'implementation' => OpenAIModel::class,
        'config' => [
            'api_key' => env('MOONSHOT_V1_8K_API_KEY', env('MOONSHOT_API_KEY')),
            'base_url' => 'https://api.moonshot.cn/v1',
        ],
    ],
];
$models = array_merge($models, $ollamaModels, $glmModels);
return [
    'llm' => [
        'default' => 'gpt-4',
        // Modify this according to your needs
        'models' => $models,
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

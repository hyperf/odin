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
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ChatglmModel;
use Hyperf\Odin\Model\DoubaoModel;
use Hyperf\Odin\Model\OpenAIModel;

use function Hyperf\Support\env;

return [
    'llm' => [
        'default' => 'gpt-4o-global',
        'general_model_options' => [
            'chat' => true,
            'function_call' => false,
            'embedding' => false,
            'multi_modal' => false,
            'vector_size' => 0,
        ],
        'general_api_options' => [
            'timeout' => [
                'connection' => 5.0,  // 连接超时（秒）
                'write' => 10.0,      // 写入超时（秒）
                'read' => 300.0,      // 读取超时（秒）
                'total' => 350.0,     // 总体超时（秒）
                'thinking' => 120.0,  // 思考超时（秒）
                'stream_chunk' => 30.0, // 流式块间超时（秒）
                'stream_first' => 60.0, // 首个流式块超时（秒）
            ],
            'custom_error_mapping_rules' => [],
            /**
             * HTTP 处理器配置
             * 'auto': 自动选择最佳处理器（默认）
             * 'curl': 强制使用 cURL（更好的性能和功能）
             * 'stream': 强制使用 PHP Stream（纯 PHP，无外部依赖）.
             */
            'http_handler' => env('ODIN_HTTP_HANDLER', 'auto'),
        ],
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
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'gpt-4o-mini-global' => [
                'implementation' => AzureOpenAIModel::class,
                'config' => [
                    'api_key' => env('AZURE_OPENAI_4O_MINI_API_KEY'),
                    'api_base' => env('AZURE_OPENAI_4O_MINI_API_BASE'),
                    'api_version' => env('AZURE_OPENAI_4O_MINI_API_VERSION'),
                    'deployment_name' => env('AZURE_OPENAI_4O_MINI_DEPLOYMENT_NAME'),
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
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'dmeta-embedding' => [
                'implementation' => OpenAIModel::class,
                'config' => [
                    'api_key' => env('MISC_API_KEY'),
                    'base_url' => env('MISC_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => false,
                    'function_call' => false,
                    'embedding' => true,
                    'multi_modal' => false,
                    'vector_size' => 768,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'glm' => [
                'implementation' => ChatglmModel::class,
                'model' => env('GLM_MODEL'),
                'config' => [
                    'api_key' => env('MISC_API_KEY'),
                    'base_url' => env('MISC_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => false,
                    'embedding' => false,
                    'multi_modal' => false,
                    'vector_size' => 0,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'Doubao-pro-32k' => [
                'implementation' => DoubaoModel::class,
                'model' => env('DOUBAO_PRO_32K_ENDPOINT'),
                'config' => [
                    'api_key' => env('DOUBAO_API_KEY'),
                    'base_url' => env('DOUBAO_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => false,
                    'embedding' => false,
                    'multi_modal' => false,
                    'vector_size' => 0,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'doubao-1.5-vision-pro-32k' => [
                'implementation' => DoubaoModel::class,
                'model' => env('DOUBAO_1_5_VISION_PRO_32K_ENDPOINT'),
                'config' => [
                    'api_key' => env('DOUBAO_API_KEY'),
                    'base_url' => env('DOUBAO_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => false,
                    'embedding' => false,
                    'multi_modal' => true,
                    'vector_size' => 0,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'deepseek-r1' => [
                'implementation' => DoubaoModel::class,
                'model' => env('DEEPSPEEK_R1_ENDPOINT'),
                'config' => [
                    'api_key' => env('DOUBAO_API_KEY'),
                    'base_url' => env('DOUBAO_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => false,
                    'embedding' => false,
                    'multi_modal' => false,
                    'vector_size' => 0,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 1800.0,       // 读取超时（秒）
                        'total' => 1850.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
                ],
            ],
            'deepseek-v3' => [
                'implementation' => DoubaoModel::class,
                'model' => env('DEEPSPEEK_V3_ENDPOINT'),
                'config' => [
                    'api_key' => env('DOUBAO_API_KEY'),
                    'base_url' => env('DOUBAO_BASE_URL'),
                ],
                'model_options' => [
                    'chat' => true,
                    'function_call' => false,
                    'embedding' => false,
                    'multi_modal' => false,
                    'vector_size' => 0,
                ],
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'custom_error_mapping_rules' => [],
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
                'api_options' => [
                    'timeout' => [
                        'connection' => 5.0,  // 连接超时（秒）
                        'write' => 10.0,      // 写入超时（秒）
                        'read' => 300.0,       // 读取超时（秒）
                        'total' => 350.0,     // 总体超时（秒）
                        'thinking' => 120.0,  // 思考超时（秒）
                        'stream_chunk' => 30.0, // 流式块间超时（秒）
                        'stream_first' => 60.0, // 首个流式块超时（秒）
                    ],
                    'proxy' => env('HTTP_CLIENT_PROXY'),
                    'custom_error_mapping_rules' => [],
                ],
            ],
        ],
        // 全局模型 options，可被模型本身的 options 覆盖
        'model_options' => [
            'error_mapping_rules' => [
                // 示例：自定义错误映射
                // '自定义错误关键词' => \Hyperf\Odin\Exception\LLMException\LLMTimeoutError::class,
            ],
        ],
    ],
    'content_copy_keys' => [
        'request-id', 'x-b3-trace-id', 'FlowEventStreamManager::EventStream',
    ],
];

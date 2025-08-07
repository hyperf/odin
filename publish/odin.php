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
            'logging' => [
                // 日志字段白名单配置
                // 如果为空数组或未配置，则打印所有字段
                // 如果配置了字段列表，则只打印指定的字段
                // 支持嵌套字段，使用点语法如 'args.messages'
                // 注意：messages 和 tools 字段不在白名单中，不会被打印
                'whitelist_fields' => [
                    // 基本请求信息
                    'model_id',                    // 模型ID
                    'model',                       // 模型名称
                    'duration_ms',                 // 请求耗时
                    'url',                         // 请求URL
                    'status_code',                 // 响应状态码

                    // 使用量统计
                    'usage',                       // 完整的usage对象
                    'usage.input_tokens',          // 输入token数量
                    'usage.output_tokens',         // 输出token数量
                    'usage.total_tokens',          // 总token数量

                    // 请求参数（排除敏感内容）
                    'args.temperature',            // 温度参数
                    'args.max_tokens',             // 最大token限制
                    'args.top_p',                  // Top-p参数
                    'args.top_k',                  // Top-k参数
                    'args.frequency_penalty',      // 频率惩罚
                    'args.presence_penalty',       // 存在惩罚
                    'args.stream',                 // 流式响应标志
                    'args.stop',                   // 停止词
                    'args.seed',                   // 随机种子

                    // Token预估信息
                    'token_estimate',              // Token估算详情
                    'token_estimate.input_tokens', // 估算输入tokens
                    'token_estimate.output_tokens', // 估算输出tokens

                    // 响应内容（排除具体内容）
                    'choices.0.finish_reason',     // 完成原因
                    'choices.0.index',             // 选择索引

                    // 错误信息
                    'error',                       // 错误详情
                    'error.type',                  // 错误类型
                    'error.message',               // 错误消息（不包含具体内容）

                    // 其他元数据
                    'created',                     // 创建时间戳
                    'id',                         // 请求ID
                    'object',                     // 对象类型
                    'system_fingerprint',         // 系统指纹
                    'performance_flag',            // 性能标记（慢请求标识）

                    // 注意：以下字段被排除，不会打印
                    // - args.messages (用户消息内容)
                    // - args.tools (工具定义)
                    // - choices.0.message (响应消息内容)
                    // - choices.0.delta (流式响应增量内容)
                    // - content (响应内容)
                ],
                // 是否启用字段白名单过滤，默认true（启用过滤）
                'enable_whitelist' => env('ODIN_LOG_WHITELIST_ENABLED', true),
            ],
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
        'request-id', 'x-b3-trace-id',
    ],
];

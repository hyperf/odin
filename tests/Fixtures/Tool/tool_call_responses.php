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
return [
    // 基本工具调用响应
    'basic_tool_call' => [
        'id' => 'chatcmpl-123456789',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4',
        'usage' => [
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ],
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_01234567890abcdef',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_weather',
                                'arguments' => json_encode([
                                    'location' => '北京',
                                    'unit' => 'celsius',
                                ]),
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
    ],

    // 多个工具调用响应
    'multiple_tool_calls' => [
        'id' => 'chatcmpl-987654321',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4',
        'usage' => [
            'prompt_tokens' => 150,
            'completion_tokens' => 80,
            'total_tokens' => 230,
        ],
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_abcdef01234567890',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_user_profile',
                                'arguments' => json_encode([
                                    'user_id' => 12345,
                                    'include_details' => true,
                                ]),
                            ],
                        ],
                        [
                            'id' => 'call_fedcba09876543210',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_user_orders',
                                'arguments' => json_encode([
                                    'user_id' => 12345,
                                    'limit' => 5,
                                ]),
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
    ],

    // 带部分内容的工具调用响应
    'mixed_content_and_tool_call' => [
        'id' => 'chatcmpl-abcdef123456',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4',
        'usage' => [
            'prompt_tokens' => 120,
            'completion_tokens' => 70,
            'total_tokens' => 190,
        ],
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => '我需要获取一些信息来回答您的问题，请稍等。',
                    'tool_calls' => [
                        [
                            'id' => 'call_12345abcdef6789',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_database',
                                'arguments' => json_encode([
                                    'query' => '最新手机型号',
                                    'category' => 'electronics',
                                    'limit' => 3,
                                ]),
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
    ],

    // 复杂嵌套参数的工具调用响应
    'complex_nested_parameters' => [
        'id' => 'chatcmpl-complex987654',
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'gpt-4',
        'usage' => [
            'prompt_tokens' => 200,
            'completion_tokens' => 150,
            'total_tokens' => 350,
        ],
        'choices' => [
            [
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_complex123456789',
                            'type' => 'function',
                            'function' => [
                                'name' => 'create_order',
                                'arguments' => json_encode([
                                    'customer' => [
                                        'name' => '张三',
                                        'email' => 'zhangsan@example.com',
                                        'phone' => '13800138000',
                                        'address' => [
                                            'street' => '中关村大街1号',
                                            'city' => '北京',
                                            'postal_code' => '100080',
                                            'country' => '中国',
                                        ],
                                    ],
                                    'items' => [
                                        [
                                            'product_id' => 1001,
                                            'quantity' => 2,
                                            'price' => 299.99,
                                            'options' => [
                                                'color' => '黑色',
                                                'size' => 'L',
                                            ],
                                        ],
                                        [
                                            'product_id' => 1002,
                                            'quantity' => 1,
                                            'price' => 99.99,
                                            'options' => [
                                                'color' => '白色',
                                            ],
                                        ],
                                    ],
                                    'payment' => [
                                        'method' => 'alipay',
                                        'currency' => 'CNY',
                                    ],
                                    'notes' => '请尽快发货，谢谢！',
                                ]),
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
    ],

    // 流式第一部分工具调用响应
    'stream_tool_call_start' => [
        'id' => 'chatcmpl-stream123456',
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => 'gpt-4',
        'choices' => [
            [
                'index' => 0,
                'delta' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        [
                            'index' => 0,
                            'id' => 'call_stream123456789',
                            'type' => 'function',
                            'function' => [
                                'name' => 'calculate',
                                'arguments' => '{"operation":"',
                            ],
                        ],
                    ],
                ],
                'finish_reason' => null,
            ],
        ],
    ],

    // 流式中间部分工具调用响应
    'stream_tool_call_middle' => [
        'id' => 'chatcmpl-stream123456',
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => 'gpt-4',
        'choices' => [
            [
                'index' => 0,
                'delta' => [
                    'tool_calls' => [
                        [
                            'index' => 0,
                            'function' => [
                                'arguments' => 'add","a":10,"b":',
                            ],
                        ],
                    ],
                ],
                'finish_reason' => null,
            ],
        ],
    ],

    // 流式结束部分工具调用响应
    'stream_tool_call_end' => [
        'id' => 'chatcmpl-stream123456',
        'object' => 'chat.completion.chunk',
        'created' => time(),
        'model' => 'gpt-4',
        'choices' => [
            [
                'index' => 0,
                'delta' => [
                    'tool_calls' => [
                        [
                            'index' => 0,
                            'function' => [
                                'arguments' => '20}',
                            ],
                        ],
                    ],
                ],
                'finish_reason' => 'tool_calls',
            ],
        ],
    ],
];

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
    // 简单工具定义 - 无参数
    'simple_no_params' => [
        'name' => 'get_server_time',
        'description' => '获取服务器当前时间',
        'parameters' => [
            'type' => 'object',
            'properties' => [],
        ],
    ],

    // 简单工具定义 - 基本参数
    'simple_basic_params' => [
        'name' => 'echo_message',
        'description' => '返回输入的消息',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => '要返回的消息',
                ],
            ],
            'required' => ['message'],
        ],
    ],

    // 中等复杂度工具定义 - 多种类型参数
    'medium_multiple_types' => [
        'name' => 'get_user_profile',
        'description' => '获取用户资料信息',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'type' => 'integer',
                    'description' => '用户ID',
                    'minimum' => 1,
                ],
                'include_details' => [
                    'type' => 'boolean',
                    'description' => '是否包含详细信息',
                ],
                'fields' => [
                    'type' => 'array',
                    'description' => '要包含的字段',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'required' => ['user_id'],
        ],
    ],

    // 复杂工具定义 - 嵌套对象和数组
    'complex_nested' => [
        'name' => 'create_order',
        'description' => '创建新订单',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'customer' => [
                    'type' => 'object',
                    'description' => '客户信息',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => '客户姓名',
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => '客户邮箱',
                            'format' => 'email',
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => '客户电话',
                        ],
                        'address' => [
                            'type' => 'object',
                            'description' => '客户地址',
                            'properties' => [
                                'street' => [
                                    'type' => 'string',
                                    'description' => '街道',
                                ],
                                'city' => [
                                    'type' => 'string',
                                    'description' => '城市',
                                ],
                                'postal_code' => [
                                    'type' => 'string',
                                    'description' => '邮政编码',
                                ],
                                'country' => [
                                    'type' => 'string',
                                    'description' => '国家',
                                ],
                            ],
                            'required' => ['street', 'city', 'country'],
                        ],
                    ],
                    'required' => ['name', 'email'],
                ],
                'items' => [
                    'type' => 'array',
                    'description' => '订单项目',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => '产品ID',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => '数量',
                                'minimum' => 1,
                            ],
                            'price' => [
                                'type' => 'number',
                                'description' => '单价',
                            ],
                            'options' => [
                                'type' => 'object',
                                'description' => '产品选项',
                                'additionalProperties' => true,
                            ],
                        ],
                        'required' => ['product_id', 'quantity'],
                    ],
                    'minItems' => 1,
                ],
                'payment' => [
                    'type' => 'object',
                    'description' => '支付信息',
                    'properties' => [
                        'method' => [
                            'type' => 'string',
                            'description' => '支付方式',
                            'enum' => ['credit_card', 'paypal', 'bank_transfer', 'alipay', 'wechat_pay'],
                        ],
                        'currency' => [
                            'type' => 'string',
                            'description' => '货币',
                            'default' => 'CNY',
                        ],
                    ],
                    'required' => ['method'],
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => '订单备注',
                ],
            ],
            'required' => ['customer', 'items'],
        ],
    ],

    // 带验证约束的工具定义
    'with_constraints' => [
        'name' => 'validate_user_input',
        'description' => '验证用户输入',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'description' => '用户名',
                    'minLength' => 3,
                    'maxLength' => 20,
                    'pattern' => '^[a-zA-Z0-9_]+$',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                    'minimum' => 18,
                    'maximum' => 120,
                ],
                'email' => [
                    'type' => 'string',
                    'description' => '电子邮件',
                    'format' => 'email',
                ],
                'website' => [
                    'type' => 'string',
                    'description' => '网站',
                    'format' => 'uri',
                ],
                'score' => [
                    'type' => 'number',
                    'description' => '分数',
                    'minimum' => 0,
                    'maximum' => 100,
                    'multipleOf' => 0.5,
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => '标签',
                    'items' => [
                        'type' => 'string',
                    ],
                    'minItems' => 1,
                    'maxItems' => 5,
                    'uniqueItems' => true,
                ],
            ],
            'required' => ['username', 'email'],
        ],
    ],

    // 带示例的工具定义
    'with_examples' => [
        'name' => 'format_data',
        'description' => '格式化数据',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'description' => '要格式化的数据',
                    'additionalProperties' => true,
                ],
                'format' => [
                    'type' => 'string',
                    'description' => '输出格式',
                    'enum' => ['json', 'xml', 'yaml', 'csv'],
                    'default' => 'json',
                ],
                'pretty' => [
                    'type' => 'boolean',
                    'description' => '是否美化输出',
                    'default' => false,
                ],
            ],
            'required' => ['data'],
        ],
        'examples' => [
            [
                'data' => [
                    'name' => '张三',
                    'age' => 30,
                    'skills' => ['PHP', 'JavaScript', 'Python'],
                ],
                'format' => 'json',
                'pretty' => true,
            ],
            [
                'data' => [
                    'products' => [
                        ['id' => 1, 'name' => '产品A', 'price' => 99.9],
                        ['id' => 2, 'name' => '产品B', 'price' => 199.9],
                    ],
                ],
                'format' => 'csv',
            ],
        ],
    ],
];

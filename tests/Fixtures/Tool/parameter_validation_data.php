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
    // 有效的参数测试数据
    'valid' => [
        'string_params' => [
            'simple' => 'hello world',
            'with_min_length' => 'abcdef', // 最小长度要求为3
            'with_max_length' => 'abcdefghij', // 最大长度要求为10
            'with_pattern' => 'user123', // 模式: ^[a-z0-9]+$
            'with_format_email' => 'test@example.com', // 格式: email
            'with_format_uri' => 'https://example.com', // 格式: uri
            'with_format_date' => '2023-01-01', // 格式: date
            'with_format_time' => '13:45:30', // 格式: time
            'with_format_date_time' => '2023-01-01T13:45:30Z', // 格式: date-time
            'with_enum' => 'option1', // 枚举: ['option1', 'option2', 'option3']
        ],
        'number_params' => [
            'simple' => 42,
            'with_min' => 10, // 最小值要求为10
            'with_max' => 90, // 最大值要求为100
            'with_multiple_of' => 15, // 倍数要求为5
            'with_exclusive_min' => 11, // 独占最小值要求为10
            'with_exclusive_max' => 99, // 独占最大值要求为100
            'with_float' => 42.5,
        ],
        'integer_params' => [
            'simple' => 42,
            'with_min' => 10, // 最小值要求为10
            'with_max' => 90, // 最大值要求为100
            'with_multiple_of' => 15, // 倍数要求为5
        ],
        'boolean_params' => [
            'true_value' => true,
            'false_value' => false,
        ],
        'array_params' => [
            'simple' => ['a', 'b', 'c'],
            'with_min_items' => ['a', 'b', 'c'], // 最小项目数要求为2
            'with_max_items' => ['a', 'b', 'c', 'd', 'e'], // 最大项目数要求为5
            'with_unique_items' => ['a', 'b', 'c', 'd', 'e'], // 要求唯一项
            'with_string_items' => ['a', 'b', 'c'],
            'with_number_items' => [1, 2, 3, 4, 5],
            'with_object_items' => [
                ['name' => 'Item 1', 'value' => 1],
                ['name' => 'Item 2', 'value' => 2],
            ],
        ],
        'object_params' => [
            'simple' => ['name' => 'Test Object', 'value' => 42],
            'with_required' => ['name' => 'Test Object', 'value' => 42], // name是必需的
            'nested' => [
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'age' => 30,
                ],
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true,
                ],
            ],
            'with_additional_properties' => [
                'name' => 'Test Object',
                'value' => 42,
                'custom_field1' => 'custom value',
                'custom_field2' => 123,
            ],
            'without_additional_properties' => [
                'name' => 'Test Object',
                'value' => 42,
            ],
        ],
    ],

    // 无效的参数测试数据
    'invalid' => [
        'string_params' => [
            'too_short' => 'ab', // 最小长度要求为3
            'too_long' => 'abcdefghijk', // 最大长度要求为10
            'invalid_pattern' => 'User-123', // 模式: ^[a-z0-9]+$
            'invalid_email' => 'not-an-email', // 格式: email
            'invalid_uri' => 'not a uri', // 格式: uri
            'invalid_date' => '2023-13-01', // 格式: date
            'invalid_time' => '25:45:30', // 格式: time
            'invalid_date_time' => '2023-01-01 13:45:30', // 格式: date-time
            'invalid_enum' => 'option4', // 枚举: ['option1', 'option2', 'option3']
            'wrong_type' => 123, // 应该是字符串
        ],
        'number_params' => [
            'too_small' => 5, // 最小值要求为10
            'too_large' => 110, // 最大值要求为100
            'not_multiple_of' => 12, // 倍数要求为5
            'equal_exclusive_min' => 10, // 独占最小值要求为10
            'equal_exclusive_max' => 100, // 独占最大值要求为100
            'wrong_type' => 'not a number', // 应该是数字
        ],
        'integer_params' => [
            'too_small' => 5, // 最小值要求为10
            'too_large' => 110, // 最大值要求为100
            'not_multiple_of' => 12, // 倍数要求为5
            'not_integer' => 42.5, // 应该是整数
            'wrong_type' => 'not an integer', // 应该是整数
        ],
        'boolean_params' => [
            'wrong_type_string' => 'true', // 应该是布尔值
            'wrong_type_number' => 1, // 应该是布尔值
        ],
        'array_params' => [
            'too_few_items' => ['a'], // 最小项目数要求为2
            'too_many_items' => ['a', 'b', 'c', 'd', 'e', 'f'], // 最大项目数要求为5
            'not_unique_items' => ['a', 'b', 'a', 'c', 'd'], // 要求唯一项
            'wrong_item_type' => ['a', 'b', 3, 'd'], // 所有项目应该是字符串
            'wrong_type' => 'not an array', // 应该是数组
        ],
        'object_params' => [
            'missing_required' => ['value' => 42], // 缺少必需的name字段
            'invalid_property_type' => ['name' => 123, 'value' => 'not a number'], // 字段类型错误
            'invalid_nested_property' => [
                'user' => [
                    'name' => 'Test User',
                    'email' => 'not-an-email', // 无效的email格式
                    'age' => 'thirty', // 应该是数字
                ],
                'preferences' => [
                    'theme' => 'invalid-theme', // 应该是枚举值
                    'notifications' => 'yes', // 应该是布尔值
                ],
            ],
            'with_prohibited_additional_properties' => [
                'name' => 'Test Object',
                'value' => 42,
                'custom_field' => 'This should not be allowed', // 不允许额外属性
            ],
            'wrong_type' => 'not an object', // 应该是对象
        ],
    ],

    // 实际工具参数验证场景
    'tool_scenarios' => [
        'weather_tool' => [
            'valid' => [
                'basic' => [
                    'location' => '北京',
                    'unit' => 'celsius',
                ],
                'only_required' => [
                    'location' => '上海',
                ],
                'with_default' => [
                    'location' => '广州',
                    'unit' => 'fahrenheit',
                ],
            ],
            'invalid' => [
                'missing_required' => [
                    'unit' => 'celsius',
                ],
                'invalid_enum' => [
                    'location' => '深圳',
                    'unit' => 'kelvin',
                ],
                'empty_location' => [
                    'location' => '',
                    'unit' => 'celsius',
                ],
            ],
        ],
        'calculator_tool' => [
            'valid' => [
                'addition' => [
                    'operation' => 'add',
                    'a' => 10,
                    'b' => 20,
                ],
                'subtraction' => [
                    'operation' => 'subtract',
                    'a' => 30,
                    'b' => 15,
                ],
                'multiplication' => [
                    'operation' => 'multiply',
                    'a' => 5,
                    'b' => 6,
                ],
                'division' => [
                    'operation' => 'divide',
                    'a' => 100,
                    'b' => 4,
                ],
            ],
            'invalid' => [
                'missing_operation' => [
                    'a' => 10,
                    'b' => 20,
                ],
                'missing_operands' => [
                    'operation' => 'add',
                ],
                'invalid_operation' => [
                    'operation' => 'power',
                    'a' => 2,
                    'b' => 3,
                ],
                'division_by_zero' => [
                    'operation' => 'divide',
                    'a' => 10,
                    'b' => 0,
                ],
                'wrong_type_operands' => [
                    'operation' => 'add',
                    'a' => 'ten',
                    'b' => 'twenty',
                ],
            ],
        ],
    ],
];

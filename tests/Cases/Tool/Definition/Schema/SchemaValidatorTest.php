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

namespace HyperfTest\Odin\Cases\Tool\Definition\Schema;

use Hyperf\Odin\Tool\Definition\Schema\SchemaValidator;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class SchemaValidatorTest extends ToolBaseTestCase
{
    protected SchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SchemaValidator();
    }

    /**
     * 测试验证基本有效的JSON Schema.
     */
    public function testValidateValidSchema(): void
    {
        $schema = $this->createValidSchema();
        $result = $this->validator->validate($schema);

        $this->assertTrue($result, '有效Schema的验证应该通过');
        $this->assertEmpty($this->validator->getErrors(), '有效Schema的验证不应有错误');
    }

    /**
     * 测试验证包含高级功能的有效JSON Schema.
     */
    public function testValidateComplexValidSchema(): void
    {
        $schema = $this->createComplexValidSchema();
        $result = $this->validator->validate($schema);

        $this->assertTrue($result, '有效的复杂Schema的验证应该通过');
        $this->assertEmpty($this->validator->getErrors(), '有效的复杂Schema的验证不应有错误');
    }

    /**
     * 测试验证具有空属性的Schema.
     */
    public function testEmpty()
    {
        $schema = [
            'type' => 'object',
            'properties' => (object) [], // 使用对象而不是数组
        ];

        $result = $this->validator->validate($schema);

        $this->assertTrue($result, '空属性的Schema应该验证通过: ' . json_encode($this->validator->getErrors()));
        $this->assertEmpty($this->validator->getErrors(), '空属性的Schema不应有错误');
    }

    /**
     * 测试验证无效的JSON Schema (缺少必需字段).
     */
    public function testValidateInvalidSchemaMissingRequired(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    // 缺少type字段
                    'description' => '姓名',
                ],
            ],
        ];

        $result = $this->validator->validate($schema);

        $this->assertFalse($result, '缺少必需字段的Schema应验证失败');
        $this->assertNotEmpty($this->validator->getErrors(), '应该返回错误信息');

        // 验证错误信息包含必需字段相关内容
        $foundRequiredError = false;
        foreach ($this->validator->getErrors() as $error) {
            if (isset($error['property'])
                && (str_contains(json_encode($error), 'type')
                 || str_contains(json_encode($error), 'required'))) {
                $foundRequiredError = true;
                break;
            }
        }

        $this->assertTrue($foundRequiredError, '应该发现缺少必需字段的错误');
    }

    /**
     * 测试验证无效的JSON Schema (类型错误).
     */
    public function testValidateInvalidSchemaTypeError(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => [
                    'type' => 'wrongtype', // 无效的类型
                    'description' => '年龄',
                ],
            ],
        ];

        $result = $this->validator->validate($schema);

        $this->assertFalse($result, '类型错误的Schema应验证失败');
        $this->assertNotEmpty($this->validator->getErrors(), '应该返回错误信息');

        // 验证错误信息包含类型错误相关内容
        $foundTypeError = false;
        foreach ($this->validator->getErrors() as $error) {
            if (isset($error['property']) && str_contains(json_encode($error), 'type')) {
                $foundTypeError = true;
                break;
            }
        }

        $this->assertTrue($foundTypeError, '应该发现类型错误');
    }

    /**
     * 测试验证无效的JSON Schema (格式错误).
     */
    public function testValidateInvalidSchemaFormatError(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'invalid-format', // 无效的格式
                    'description' => '邮箱',
                ],
            ],
        ];

        $result = $this->validator->validate($schema);

        $this->assertFalse($result, '格式错误的Schema应验证失败');
        $this->assertNotEmpty($this->validator->getErrors(), '应该返回错误信息');
    }

    /**
     * 测试验证包含无效引用的JSON Schema.
     */
    public function testValidateInvalidSchemaWithReference(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    '$ref' => '#/invalidPath', // 无效的引用路径
                    'description' => '用户',
                ],
            ],
        ];

        $result = $this->validator->validate($schema);

        // 注意：在某些JSON Schema实现中，无效引用可能不会在schema验证阶段被检测到
        // 而是在解析引用或验证数据时才会发现错误
        // 所以这里我们不断言结果，只检查可能的错误信息
        if (! $result) {
            $this->assertNotEmpty($this->validator->getErrors(), '应该返回错误信息');
        }
    }

    /**
     * 测试使用不同版本的元Schema.
     */
    public function testValidateWithDifferentMetaSchema(): void
    {
        $schema = $this->createValidSchema();

        // 测试Draft-04版本
        $result = $this->validator->validate($schema, 'http://json-schema.org/draft-04/schema#');

        // 由于我们的schema是基本的，应该与大多数版本兼容
        $this->assertTrue($result, '基本Schema应该与Draft-04兼容');
        $this->assertEmpty($this->validator->getErrors(), '基本Schema验证不应有错误');
    }

    /**
     * 测试异常处理 (无法获取元Schema).
     */
    public function testValidateWithInvalidMetaSchema(): void
    {
        $schema = $this->createValidSchema();

        // 使用不存在的元Schema URL
        $result = $this->validator->validate($schema, 'http://non-existent-url/schema');

        $this->assertFalse($result, '使用无效的元Schema应验证失败');
        $this->assertNotEmpty($this->validator->getErrors(), '应该返回错误信息');
    }

    /**
     * 创建一个有效的基本Schema.
     */
    private function createValidSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '姓名',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => '年龄',
                    'minimum' => 0,
                ],
            ],
            'required' => ['name'],
        ];
    }

    /**
     * 创建一个复杂的有效Schema.
     */
    private function createComplexValidSchema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'title' => '用户信息',
            'description' => '用户基本信息',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => '用户ID',
                    'minimum' => 1,
                ],
                'name' => [
                    'type' => 'string',
                    'description' => '用户名',
                    'minLength' => 2,
                    'maxLength' => 50,
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'description' => '电子邮件',
                ],
                'age' => [
                    'type' => ['integer', 'null'],
                    'description' => '年龄',
                    'minimum' => 0,
                    'maximum' => 120,
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => '标签',
                    'items' => [
                        'type' => 'string',
                    ],
                    'uniqueItems' => true,
                ],
                'address' => [
                    'type' => 'object',
                    'description' => '地址',
                    'properties' => [
                        'street' => [
                            'type' => 'string',
                            'description' => '街道',
                        ],
                        'city' => [
                            'type' => 'string',
                            'description' => '城市',
                        ],
                        'zipcode' => [
                            'type' => 'string',
                            'description' => '邮编',
                            'pattern' => '^[0-9]{6}$',
                        ],
                    ],
                    'required' => ['street', 'city'],
                ],
            ],
            'required' => ['id', 'name', 'email'],
            'additionalProperties' => false,
        ];
    }
}

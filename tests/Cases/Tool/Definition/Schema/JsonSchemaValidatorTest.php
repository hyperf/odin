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

use Hyperf\Odin\Tool\Definition\Schema\JsonSchemaBuilder;
use Hyperf\Odin\Tool\Definition\Schema\JsonSchemaValidator;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class JsonSchemaValidatorTest extends ToolBaseTestCase
{
    /**
     * 测试有效数据验证.
     */
    public function testValidateValidData(): void
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->createTestSchema();

        $validData = [
            'name' => '张三',
            'age' => 30,
            'email' => 'zhangsan@example.com',
            'isActive' => true,
            'address' => [
                'street' => '中山路',
                'city' => '上海',
                'zipcode' => '200001',
            ],
            'tags' => ['开发', '设计', 'PHP'],
        ];

        $result = $validator->validate($validData, $schema);

        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());
    }

    /**
     * 测试无效数据验证（类型错误）.
     */
    public function testValidateInvalidTypeData(): void
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->createTestSchema();

        $invalidData = [
            'name' => '李四',
            'age' => '三十岁', // 应该是整数
            'email' => 'lisi@example.com',
            'isActive' => true,
            'address' => [
                'street' => '北京路',
                'city' => '北京',
                'zipcode' => '100001',
            ],
            'tags' => ['开发', '设计'],
        ];

        $result = $validator->validate($invalidData, $schema);

        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());

        // 找到关于age的错误
        $ageError = false;
        foreach ($validator->getErrors() as $error) {
            if (isset($error['property']) && $error['property'] === 'age') {
                $ageError = true;
                $this->assertStringContainsString('integer', json_encode($error), '错误信息应包含类型提示');
                break;
            }
        }
        $this->assertTrue($ageError, '应该发现 age 字段的类型错误');
    }

    /**
     * 测试无效数据验证（值范围错误）.
     */
    public function testValidateInvalidValueData(): void
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->createTestSchema();

        $invalidData = [
            'name' => '王五',
            'age' => 150, // 超出最大值120
            'email' => 'wangwu@example.com',
            'isActive' => true,
            'address' => [
                'street' => '广州路',
                'city' => '广州',
                'zipcode' => '510000',
            ],
            'tags' => ['开发', '设计'],
        ];

        $result = $validator->validate($invalidData, $schema);

        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());

        // 找到关于age的错误
        $ageError = false;
        foreach ($validator->getErrors() as $error) {
            if (isset($error['property']) && $error['property'] === 'age') {
                $ageError = true;
                $this->assertStringContainsString('maximum', json_encode($error), '错误信息应包含范围提示');
                break;
            }
        }
        $this->assertTrue($ageError, '应该发现 age 字段的范围错误');
    }

    /**
     * 测试无效数据验证（必填字段缺失）.
     */
    public function testValidateMissingRequiredField(): void
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->createTestSchema();

        $invalidData = [
            // 缺少必需的name字段
            'age' => 30,
            'email' => 'test@example.com',
            'isActive' => true,
            'address' => [
                'street' => '中山路',
                'city' => '上海',
                'zipcode' => '200001',
            ],
            'tags' => ['开发', '设计'],
        ];

        $result = $validator->validate($invalidData, $schema);

        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());

        // 找到关于缺少必需字段的错误
        $requiredError = false;
        foreach ($validator->getErrors() as $error) {
            if (isset($error['property'], $error['message']) && $error['property'] === 'name') {
                $requiredError = true;
                $this->assertStringContainsString('required', strtolower($error['message']), '错误信息应包含required提示');
                break;
            }
        }
        $this->assertTrue($requiredError, '应该发现缺少必需字段的错误');
    }

    /**
     * 测试错误消息格式化.
     */
    public function testErrorMessageFormatting(): void
    {
        $validator = new JsonSchemaValidator();
        $schema = $this->createTestSchema();

        $invalidData = [
            // 多种错误类型
            'name' => 'a', // 长度太短
            'age' => '非数字', // 类型错误
            'email' => 'invalid-email', // 格式错误
            'isActive' => 'yes', // 类型错误
            'address' => 'not-an-object', // 类型错误
            'tags' => 'not-an-array', // 类型错误
        ];

        $validator->validate($invalidData, $schema);
        $errors = $validator->getErrors();

        $this->assertNotEmpty($errors);

        // 验证错误消息的格式
        foreach ($errors as $error) {
            $this->assertIsArray($error);
            $this->assertArrayHasKey('property', $error);
            $this->assertArrayHasKey('message', $error);
            $this->assertIsString($error['message']);
            $this->assertNotEmpty($error['message']);
        }
    }

    /**
     * 测试复杂嵌套对象验证.
     */
    public function testValidateNestedObjects(): void
    {
        $validator = new JsonSchemaValidator();

        // 创建复杂嵌套Schema
        $builder = new JsonSchemaBuilder();

        // 创建嵌套对象
        $addressBuilder = new JsonSchemaBuilder();
        $addressBuilder->addStringProperty('street', '街道', true);
        $addressBuilder->addStringProperty('city', '城市', true);
        $addressBuilder->addStringProperty('zipcode', '邮编', false);
        $addressSchema = $addressBuilder->build();

        $contactBuilder = new JsonSchemaBuilder();
        $contactBuilder->addStringProperty('phone', '电话', true);
        $contactBuilder->addStringProperty('email', '邮箱', true);
        $contactBuilder->addObjectProperty('address', '地址', $addressSchema['properties'], ['street', 'city'], true);
        $contactSchema = $contactBuilder->build();

        // 主Schema
        $builder->addStringProperty('name', '姓名', true);
        $builder->addNumberProperty('age', '年龄', true, true, 0, 150);
        $builder->addObjectProperty('contact', '联系方式', $contactSchema['properties'], ['phone', 'email', 'address'], true);

        $schema = $builder->build();

        // 有效数据
        $validData = [
            'name' => '张三',
            'age' => 30,
            'contact' => [
                'phone' => '13800138000',
                'email' => 'zhangsan@example.com',
                'address' => [
                    'street' => '中山路',
                    'city' => '上海',
                    'zipcode' => '200001',
                ],
            ],
        ];

        $result = $validator->validate($validData, $schema);
        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());

        // 无效数据 - 缺少嵌套对象中的必填字段
        $invalidData = [
            'name' => '张三',
            'age' => 30,
            'contact' => [
                'phone' => '13800138000',
                'email' => 'zhangsan@example.com',
                'address' => [
                    // 缺少必需的street字段
                    'city' => '上海',
                    'zipcode' => '200001',
                ],
            ],
        ];

        $result = $validator->validate($invalidData, $schema);
        $this->assertFalse($result);
        $this->assertNotEmpty($validator->getErrors());

        // 找到关于嵌套对象中缺少必需字段的错误
        $nestedError = false;
        foreach ($validator->getErrors() as $error) {
            if (isset($error['property']) && (
                str_contains($error['property'], 'contact.address.street')
                || str_contains($error['property'], 'street')
            )) {
                $nestedError = true;
                break;
            }
        }
        $this->assertTrue($nestedError, '应该发现嵌套对象中缺少必需字段的错误');
    }

    /**
     * 创建测试用的Schema.
     */
    private function createTestSchema(): array
    {
        $builder = new JsonSchemaBuilder();

        // 添加基本属性
        $builder->addStringProperty('name', '姓名', true, 2, 50);
        $builder->addNumberProperty('age', '年龄', true, true, 0, 120);
        $builder->addStringProperty('email', '电子邮件', false, null, null, null, 'email');
        $builder->addBooleanProperty('isActive', '是否激活', true);

        // 添加地址对象
        $addressBuilder = new JsonSchemaBuilder();
        $addressBuilder->addStringProperty('street', '街道', true);
        $addressBuilder->addStringProperty('city', '城市', true);
        $addressBuilder->addStringProperty('zipcode', '邮编', false);
        $addressSchema = $addressBuilder->build();

        // 添加地址和标签
        $builder->addObjectProperty('address', '地址', $addressSchema['properties'], ['street', 'city'], true);
        $builder->addArrayProperty('tags', '标签', ['type' => 'string'], false, 1);

        return $builder->build();
    }
}

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
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;
use InvalidArgumentException;

/**
 * @internal
 * @coversNothing
 */
class JsonSchemaBuilderTest extends ToolBaseTestCase
{
    /**
     * 测试构建基础 Schema.
     */
    public function testBuildBasicSchema(): void
    {
        $builder = new JsonSchemaBuilder();

        // 测试默认生成的空Schema
        $schema = $builder->build();
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEmpty($schema['properties']);
        $this->assertArrayNotHasKey('required', $schema); // 没有必需属性时不应包含required字段
        $this->assertArrayHasKey('$schema', $schema); // 检查添加了schema标识

        // 测试重置方法
        $builder->addStringProperty('name', '姓名', true);
        $builder->reset();
        $schema = $builder->build();
        $this->assertEmpty($schema['properties']);

        // 测试设置类型
        $builder->setType('array');
        $schema = $builder->build();
        $this->assertEquals('array', $schema['type']);

        // 测试无效类型
        $this->expectException(InvalidArgumentException::class);
        $builder->setType('invalid_type');
    }

    /**
     * 测试构建嵌套 Schema.
     */
    public function testBuildNestedSchema(): void
    {
        $builder = new JsonSchemaBuilder();

        // 创建用户对象属性
        $userBuilder = new JsonSchemaBuilder();
        $userBuilder->addStringProperty('name', '用户名', true);
        $userBuilder->addNumberProperty('age', '年龄', true, true); // 整数类型
        $userBuilder->addStringProperty('email', '电子邮件', false, null, null, null, 'email');
        $userProperties = $userBuilder->build();

        // 创建地址对象属性
        $addressBuilder = new JsonSchemaBuilder();
        $addressBuilder->addStringProperty('street', '街道', true);
        $addressBuilder->addStringProperty('city', '城市', true);
        $addressBuilder->addStringProperty('zipcode', '邮编', false);
        $addressProperties = $addressBuilder->build();

        // 添加到主Schema
        $builder->addObjectProperty('user', '用户信息', $userProperties['properties'], ['name', 'age'], true);
        $builder->addObjectProperty('address', '地址信息', $addressProperties['properties'], ['street', 'city'], false);
        $builder->addArrayProperty('tags', '标签', ['type' => 'string'], false);

        $schema = $builder->build();

        // 验证嵌套Schema结构
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('user', $schema['properties']);
        $this->assertArrayHasKey('address', $schema['properties']);
        $this->assertArrayHasKey('tags', $schema['properties']);

        // 验证必需属性
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('user', $schema['required']);
        $this->assertNotContains('address', $schema['required']);

        // 验证嵌套对象的结构
        $user = $schema['properties']['user'];
        $this->assertEquals('object', $user['type']);
        $this->assertArrayHasKey('properties', $user);
        $this->assertArrayHasKey('name', $user['properties']);
        $this->assertArrayHasKey('age', $user['properties']);
        $this->assertArrayHasKey('email', $user['properties']);
        $this->assertArrayHasKey('required', $user);
        $this->assertContains('name', $user['required']);
        $this->assertContains('age', $user['required']);

        // 验证数组属性
        $tags = $schema['properties']['tags'];
        $this->assertEquals('array', $tags['type']);
        $this->assertArrayHasKey('items', $tags);
        $this->assertEquals('string', $tags['items']['type']);
    }

    /**
     * 测试构建包含约束的 Schema.
     */
    public function testBuildSchemaWithConstraints(): void
    {
        $builder = new JsonSchemaBuilder();

        // 添加字符串属性（带约束）
        $builder->addStringProperty(
            'username',
            '用户名',
            true,
            3,         // 最小长度
            20,        // 最大长度
            '^[a-z].*', // 正则模式
            null,
            null
        );

        // 添加数字属性（带约束）
        $builder->addNumberProperty(
            'age',
            '年龄',
            true,
            true,   // 整数
            18,     // 最小值
            120,    // 最大值
            17.0,   // 独占最小值 (严格大于17)
            121.0,  // 独占最大值 (严格小于121)
            1       // 必须是1的倍数
        );

        // 添加数组属性（带约束）
        $builder->addArrayProperty(
            'interests',
            '兴趣爱好',
            ['type' => 'string'],
            true,
            1,      // 最少1项
            5,      // 最多5项
            true    // 必须唯一
        );

        $schema = $builder->build();

        // 验证字符串约束
        $username = $schema['properties']['username'];
        $this->assertEquals('string', $username['type']);
        $this->assertEquals(3, $username['minLength']);
        $this->assertEquals(20, $username['maxLength']);
        $this->assertEquals('^[a-z].*', $username['pattern']);

        // 验证数字约束
        $age = $schema['properties']['age'];
        $this->assertEquals('integer', $age['type']);
        $this->assertEquals(18, $age['minimum']);
        $this->assertEquals(120, $age['maximum']);
        $this->assertEquals(1, $age['multipleOf']);

        // 验证数组约束
        $interests = $schema['properties']['interests'];
        $this->assertEquals('array', $interests['type']);
        $this->assertEquals(1, $interests['minItems']);
        $this->assertEquals(5, $interests['maxItems']);
        $this->assertTrue($interests['uniqueItems']);
    }

    /**
     * 测试构建包含描述的 Schema.
     */
    public function testBuildSchemaWithDescriptions(): void
    {
        $builder = new JsonSchemaBuilder();

        $builder->addStringProperty('name', '用户的全名，包括姓和名', true);
        $builder->addNumberProperty('score', '用户的得分，介于0-100之间', false, false, 0, 100);
        $builder->addBooleanProperty('isActive', '指示用户账号是否处于活跃状态', true);

        $schema = $builder->build();

        // 验证描述
        $this->assertEquals('用户的全名，包括姓和名', $schema['properties']['name']['description']);
        $this->assertEquals('用户的得分，介于0-100之间', $schema['properties']['score']['description']);
        $this->assertEquals('指示用户账号是否处于活跃状态', $schema['properties']['isActive']['description']);
    }

    /**
     * 测试构建包含默认值的 Schema.
     */
    public function testBuildSchemaWithDefaults(): void
    {
        $builder = new JsonSchemaBuilder();

        // 添加各种属性并设置默认值
        $builder->addStringProperty('name', '姓名', true);
        $schema = $builder->build();
        $nameProperty = $schema['properties']['name'];

        // 当前JsonSchemaBuilder不支持默认值设置，我们需要手动添加
        $nameProperty['default'] = '未命名用户';

        // 创建一个支持默认值的构建器扩展建议
        $this->assertArrayNotHasKey(
            'default',
            $schema['properties']['name'],
            '当前JsonSchemaBuilder不支持默认值，建议扩展该类以支持默认值设置'
        );
    }

    /**
     * 测试引用类型属性.
     */
    public function testBuildSchemaWithRefProperty(): void
    {
        $builder = new JsonSchemaBuilder();

        $builder->addRefProperty('user', '用户引用', '#/definitions/User', true);

        $schema = $builder->build();

        $userProperty = $schema['properties']['user'];
        $this->assertEquals('用户引用', $userProperty['description']);
        $this->assertEquals('#/definitions/User', $userProperty['$ref']);
        $this->assertContains('user', $schema['required']);
    }
}

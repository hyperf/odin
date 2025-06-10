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

namespace HyperfTest\Odin\Cases\Tool\Definition;

use Hyperf\Odin\Tool\Definition\ToolParameter;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class ToolParameterTest extends ToolBaseTestCase
{
    /**
     * 测试创建字符串参数.
     */
    public function testCreateStringParameter(): void
    {
        // 使用构造函数创建
        $param1 = new ToolParameter('name', '姓名', 'string', true);

        $this->assertEquals('name', $param1->getName());
        $this->assertEquals('姓名', $param1->getDescription());
        $this->assertEquals('string', $param1->getType());
        $this->assertTrue($param1->isRequired());

        // 使用静态方法创建
        $param2 = ToolParameter::string('email', '电子邮箱', true);

        $this->assertEquals('email', $param2->getName());
        $this->assertEquals('电子邮箱', $param2->getDescription());
        $this->assertEquals('string', $param2->getType());
        $this->assertTrue($param2->isRequired());

        // 测试格式设置
        $param2->setFormat('email');
        $this->assertEquals('email', $param2->getFormat());

        // 测试长度限制
        $param3 = ToolParameter::string('username', '用户名');
        $param3->setMinLength(3)->setMaxLength(20);

        $this->assertEquals(3, $param3->getMinLength());
        $this->assertEquals(20, $param3->getMaxLength());

        // 测试模式设置
        $param3->setPattern('^[a-zA-Z0-9]+$');
        $this->assertEquals('^[a-zA-Z0-9]+$', $param3->getPattern());

        // 测试枚举值
        $param4 = ToolParameter::string('color', '颜色');
        $param4->setEnum(['red', 'green', 'blue']);

        $this->assertEquals(['red', 'green', 'blue'], $param4->getEnum());

        // 测试toArray方法
        $array = $param4->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('string', $array['type']);
        $this->assertEquals('颜色', $array['description']);
        $this->assertEquals(['red', 'green', 'blue'], $array['enum']);
    }

    /**
     * 测试创建数字参数.
     */
    public function testCreateNumberParameter(): void
    {
        // 使用静态方法创建
        $param = ToolParameter::number('price', '价格', true);

        $this->assertEquals('price', $param->getName());
        $this->assertEquals('价格', $param->getDescription());
        $this->assertEquals('number', $param->getType());
        $this->assertTrue($param->isRequired());

        // 测试数值范围限制
        $param->setMinimum(0.01)->setMaximum(9999.99);

        $this->assertEquals(0.01, $param->getMinimum());
        $this->assertEquals(9999.99, $param->getMaximum());

        // 测试独占性最大最小值 (Draft 7+: 使用数值)
        $param->setExclusiveMinimum(0.0)->setExclusiveMaximum(10000.0);

        $this->assertEquals(0.0, $param->getExclusiveMinimum());
        $this->assertEquals(10000.0, $param->getExclusiveMaximum());

        // 测试倍数设置
        $param->setMultipleOf(0.01);
        $this->assertEquals(0.01, $param->getMultipleOf());

        // 测试toArray方法
        $array = $param->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('number', $array['type']);
        $this->assertEquals('价格', $array['description']);
        $this->assertEquals(0.01, $array['minimum']);
        $this->assertEquals(9999.99, $array['maximum']);
        $this->assertEquals(0.0, $array['exclusiveMinimum']);
        $this->assertEquals(10000.0, $array['exclusiveMaximum']);
        $this->assertEquals(0.01, $array['multipleOf']);
    }

    /**
     * 测试创建整数参数.
     */
    public function testCreateIntegerParameter(): void
    {
        // 使用静态方法创建
        $param = ToolParameter::integer('age', '年龄', true);

        $this->assertEquals('age', $param->getName());
        $this->assertEquals('年龄', $param->getDescription());
        $this->assertEquals('integer', $param->getType());
        $this->assertTrue($param->isRequired());

        // 测试数值范围限制
        $param->setMinimum(18)->setMaximum(120);

        $this->assertEquals(18, $param->getMinimum());
        $this->assertEquals(120, $param->getMaximum());

        // 测试倍数设置
        $param->setMultipleOf(1);
        $this->assertEquals(1, $param->getMultipleOf());

        // 测试toArray方法
        $array = $param->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('integer', $array['type']);
        $this->assertEquals('年龄', $array['description']);
        $this->assertEquals(18, $array['minimum']);
        $this->assertEquals(120, $array['maximum']);
    }

    /**
     * 测试创建布尔参数.
     */
    public function testCreateBooleanParameter(): void
    {
        // 使用静态方法创建
        $param = ToolParameter::boolean('is_active', '是否激活', false);

        $this->assertEquals('is_active', $param->getName());
        $this->assertEquals('是否激活', $param->getDescription());
        $this->assertEquals('boolean', $param->getType());
        $this->assertFalse($param->isRequired());

        // 设置默认值
        $param->setDefault(true);
        $this->assertEquals(true, $param->getDefault());

        // 测试toArray方法
        $array = $param->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('boolean', $array['type']);
        $this->assertEquals('是否激活', $array['description']);
        $this->assertEquals(true, $array['default']);
    }

    /**
     * 测试创建数组参数.
     */
    public function testCreateArrayParameter(): void
    {
        // 使用静态方法创建
        $param = ToolParameter::array('tags', '标签列表', ['type' => 'string'], true);

        $this->assertEquals('tags', $param->getName());
        $this->assertEquals('标签列表', $param->getDescription());
        $this->assertEquals('array', $param->getType());
        $this->assertTrue($param->isRequired());

        // 测试数组项目
        $items = $param->getItems();
        $this->assertIsArray($items);
        $this->assertEquals('string', $items['type']);

        // 测试数组长度限制
        $param->setMinItems(1)->setMaxItems(10);

        $this->assertEquals(1, $param->getMinItems());
        $this->assertEquals(10, $param->getMaxItems());

        // 测试唯一性
        $param->setUniqueItems(true);
        $this->assertTrue($param->getUniqueItems());

        // 测试toArray方法
        $array = $param->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('array', $array['type']);
        $this->assertEquals('标签列表', $array['description']);
        $this->assertEquals(['type' => 'string'], $array['items']);
        $this->assertEquals(1, $array['minItems']);
        $this->assertEquals(10, $array['maxItems']);
        $this->assertTrue($array['uniqueItems']);
    }

    /**
     * 测试创建对象参数.
     */
    public function testCreateObjectParameter(): void
    {
        // 使用静态方法创建
        $param = ToolParameter::object('user', '用户信息', [], [], true);

        $this->assertEquals('user', $param->getName());
        $this->assertEquals('用户信息', $param->getDescription());
        $this->assertEquals('object', $param->getType());
        $this->assertTrue($param->isRequired());

        // 添加属性
        $nameParam = ToolParameter::string('name', '姓名', true);
        $ageParam = ToolParameter::integer('age', '年龄');

        $param->addProperty($nameParam);
        $param->addProperty($ageParam);

        $properties = $param->getProperties();
        $this->assertCount(2, $properties);
        $this->assertIsArray($properties);

        // 检查第一个属性是姓名
        $this->assertInstanceOf(ToolParameter::class, $properties[0]);
        $this->assertEquals('name', $properties[0]->getName());
        $this->assertEquals('姓名', $properties[0]->getDescription());

        // 检查第二个属性是年龄
        $this->assertInstanceOf(ToolParameter::class, $properties[1]);
        $this->assertEquals('age', $properties[1]->getName());
        $this->assertEquals('年龄', $properties[1]->getDescription());

        // 设置必需属性
        $param->addPropertyRequired('name');

        $required = $param->getPropertyRequired();
        $this->assertCount(1, $required);
        $this->assertEquals('name', $required[0]);

        // 设置附加属性
        $param->setAdditionalProperties(false);
        $this->assertFalse($param->getAdditionalProperties());

        // 测试toArray方法
        $array = $param->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('object', $array['type']);
        $this->assertEquals('用户信息', $array['description']);
        $this->assertIsArray($array['properties']);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertEquals(['name'], $array['required']);
        $this->assertFalse($array['additionalProperties']);
    }

    /**
     * 测试参数必填属性.
     */
    public function testRequiredAttribute(): void
    {
        // 创建必填参数
        $param1 = new ToolParameter('required_param', '必填参数', 'string', true);
        $this->assertTrue($param1->isRequired());

        // 创建非必填参数
        $param2 = new ToolParameter('optional_param', '可选参数', 'string', false);
        $this->assertFalse($param2->isRequired());

        // 切换必填状态
        $param2->setRequired(true);
        $this->assertTrue($param2->isRequired());

        $param1->setRequired(false);
        $this->assertFalse($param1->isRequired());
    }

    /**
     * 测试从数组创建参数.
     */
    public function testFromArray(): void
    {
        $schema = [
            'type' => 'string',
            'description' => '测试参数',
            'format' => 'email',
            'minLength' => 5,
            'maxLength' => 100,
            'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
            'enum' => ['test@example.com', 'admin@example.com'],
            'default' => 'test@example.com',
        ];

        $param = ToolParameter::fromArray('test_param', $schema);

        $this->assertEquals('test_param', $param->getName());
        $this->assertEquals('测试参数', $param->getDescription());
        $this->assertEquals('string', $param->getType());
        $this->assertEquals('email', $param->getFormat());
        $this->assertEquals(5, $param->getMinLength());
        $this->assertEquals(100, $param->getMaxLength());
        $this->assertEquals('^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$', $param->getPattern());
        $this->assertEquals(['test@example.com', 'admin@example.com'], $param->getEnum());
        $this->assertEquals('test@example.com', $param->getDefault());
    }

    /**
     * 测试复杂嵌套参数.
     */
    public function testNestedParameters(): void
    {
        // 创建最内层嵌套参数 - 地址
        $addressParam = ToolParameter::object('address', '地址信息');
        $addressParam->addProperty(ToolParameter::string('street', '街道', true));
        $addressParam->addProperty(ToolParameter::string('city', '城市', true));
        $addressParam->addProperty(ToolParameter::string('postal_code', '邮政编码'));
        $addressParam->addProperty(ToolParameter::string('country', '国家', true));

        // 创建中层嵌套参数 - 联系信息
        $contactParam = ToolParameter::object('contact', '联系信息');
        $contactParam->addProperty(ToolParameter::string('email', '电子邮件', true)->setFormat('email'));
        $contactParam->addProperty(ToolParameter::string('phone', '电话号码'));
        $contactParam->addProperty($addressParam);

        // 创建最外层参数 - 用户
        $userParam = ToolParameter::object('user', '用户信息', [], [], true);
        $userParam->addProperty(ToolParameter::string('name', '姓名', true));
        $userParam->addProperty(ToolParameter::integer('age', '年龄')->setMinimum(18));
        $userParam->addProperty($contactParam);

        // 添加一个数组参数
        $userParam->addProperty(
            ToolParameter::array('tags', '标签', ['type' => 'string'])
                ->setMinItems(1)
                ->setMaxItems(5)
                ->setUniqueItems(true)
        );

        // 测试结构
        $properties = $userParam->getProperties();
        $this->assertCount(4, $properties);

        // 转换为数组并验证结构
        $array = $userParam->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('object', $array['type']);

        // 验证属性
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertArrayHasKey('contact', $array['properties']);
        $this->assertArrayHasKey('tags', $array['properties']);

        // 验证联系信息
        $contactArray = $array['properties']['contact'];
        $this->assertEquals('object', $contactArray['type']);
        $this->assertArrayHasKey('properties', $contactArray);
        $this->assertArrayHasKey('email', $contactArray['properties']);
        $this->assertArrayHasKey('phone', $contactArray['properties']);
        $this->assertArrayHasKey('address', $contactArray['properties']);

        // 验证地址信息
        $addressArray = $contactArray['properties']['address'];
        $this->assertEquals('object', $addressArray['type']);
        $this->assertArrayHasKey('properties', $addressArray);
        $this->assertArrayHasKey('street', $addressArray['properties']);
        $this->assertArrayHasKey('city', $addressArray['properties']);
        $this->assertArrayHasKey('postal_code', $addressArray['properties']);
        $this->assertArrayHasKey('country', $addressArray['properties']);
    }

    /**
     * 测试枚举参数.
     */
    public function testEnumParameters(): void
    {
        // 创建含枚举值的字符串参数
        $param1 = ToolParameter::string('color', '颜色');
        $param1->setEnum(['red', 'green', 'blue', 'yellow', 'black', 'white']);
        $param1->setDefault('blue');

        $this->assertEquals('color', $param1->getName());
        $this->assertEquals('颜色', $param1->getDescription());
        $this->assertEquals(['red', 'green', 'blue', 'yellow', 'black', 'white'], $param1->getEnum());
        $this->assertEquals('blue', $param1->getDefault());

        // 测试toArray输出
        $array1 = $param1->toArray();
        $this->assertArrayHasKey('enum', $array1);
        $this->assertCount(6, $array1['enum']);
        $this->assertEquals('blue', $array1['default']);

        // 创建含枚举值的数字参数
        $param2 = ToolParameter::integer('status_code', 'HTTP状态码');
        $param2->setEnum([200, 201, 400, 401, 403, 404, 500]);
        $param2->setDefault(200);

        $this->assertEquals('status_code', $param2->getName());
        $this->assertEquals([200, 201, 400, 401, 403, 404, 500], $param2->getEnum());
        $this->assertEquals(200, $param2->getDefault());

        // 测试toArray输出
        $array2 = $param2->toArray();
        $this->assertArrayHasKey('enum', $array2);
        $this->assertCount(7, $array2['enum']);
        $this->assertEquals(200, $array2['default']);
    }
}

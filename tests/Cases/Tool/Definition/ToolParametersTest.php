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
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class ToolParametersTest extends ToolBaseTestCase
{
    /**
     * 测试从数组构造参数.
     */
    public function testConstructFromArray(): void
    {
        // 基本构造测试
        $params = new ToolParameters(
            [],
            'object',
            '用户参数',
            '用户相关的参数集合'
        );

        $this->assertEquals('object', $params->getType());
        $this->assertEquals('用户参数', $params->getTitle());
        $this->assertEquals('用户相关的参数集合', $params->getDescription());
        $this->assertEmpty($params->getProperties());
        $this->assertEmpty($params->getRequired());

        // 使用参数数组构造
        $nameParam = ToolParameter::string('name', '姓名', true);
        $ageParam = ToolParameter::integer('age', '年龄');

        $params2 = new ToolParameters(
            [$nameParam, $ageParam],
            'object',
            'User Parameters',
            'Parameters for user information'
        );

        $properties = $params2->getProperties();
        $this->assertCount(2, $properties);

        // 验证必需参数
        $required = $params2->getRequired();
        $this->assertCount(1, $required);
        $this->assertEquals('name', $required[0]);

        // 测试toArray方法
        $array = $params2->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('object', $array['type']);
        $this->assertEquals('User Parameters', $array['title']);
        $this->assertEquals('Parameters for user information', $array['description']);
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertEquals(['name'], $array['required']);
    }

    /**
     * 测试从JSON Schema构造参数.
     */
    public function testConstructFromJsonSchema(): void
    {
        $schema = [
            'type' => 'object',
            'title' => '测试参数',
            'description' => '测试参数描述',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => '姓名',
                    'minLength' => 2,
                    'maxLength' => 50,
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
            ],
            'required' => ['name', 'email'],
            'additionalProperties' => false,
        ];

        $params = ToolParameters::fromArray($schema);

        $this->assertEquals('object', $params->getType());
        $this->assertEquals('测试参数', $params->getTitle());
        $this->assertEquals('测试参数描述', $params->getDescription());
        $this->assertFalse($params->getAdditionalProperties());

        // 验证属性
        $properties = $params->getProperties();
        $this->assertCount(3, $properties);

        // 验证必需属性
        $required = $params->getRequired();
        $this->assertCount(2, $required);
        $this->assertEquals(['name', 'email'], $required);

        // 验证属性内容
        foreach ($properties as $property) {
            $this->assertInstanceOf(ToolParameter::class, $property);
        }

        // 找到name属性并验证
        $nameFound = false;
        foreach ($properties as $property) {
            if ($property->getName() === 'name') {
                $nameFound = true;
                $this->assertEquals('string', $property->getType());
                $this->assertEquals('姓名', $property->getDescription());
                $this->assertEquals(2, $property->getMinLength());
                $this->assertEquals(50, $property->getMaxLength());
                break;
            }
        }
        $this->assertTrue($nameFound, '没有找到name属性');
    }

    /**
     * 测试Getter和Setter方法.
     */
    public function testGetterAndSetterMethods(): void
    {
        $params = new ToolParameters();

        // 测试type属性
        $this->assertEquals('object', $params->getType()); // 默认值
        $params->setType('array');
        $this->assertEquals('array', $params->getType());

        // 测试title属性
        $this->assertNull($params->getTitle()); // 默认值
        $params->setTitle('测试标题');
        $this->assertEquals('测试标题', $params->getTitle());

        // 测试description属性
        $this->assertNull($params->getDescription()); // 默认值
        $params->setDescription('测试描述');
        $this->assertEquals('测试描述', $params->getDescription());

        // 测试additionalProperties属性
        $this->assertNull($params->getAdditionalProperties()); // 默认值
        $params->setAdditionalProperties(false);
        $this->assertFalse($params->getAdditionalProperties());

        // 测试required属性
        $this->assertEmpty($params->getRequired()); // 默认值
        $params->setRequired(['name', 'email']);
        $this->assertEquals(['name', 'email'], $params->getRequired());
    }

    /**
     * 测试属性操作方法.
     */
    public function testPropertyOperations(): void
    {
        $params = new ToolParameters();
        $this->assertEmpty($params->getProperties());

        // 添加单个属性
        $nameParam = ToolParameter::string('name', '姓名', true);
        $params->addProperty($nameParam);

        $properties = $params->getProperties();
        $this->assertCount(1, $properties);
        $this->assertContains($nameParam, $properties);

        // 添加多个属性
        $ageParam = ToolParameter::integer('age', '年龄');
        $emailParam = ToolParameter::string('email', '电子邮件', true);

        $params->setProperties([$nameParam, $ageParam, $emailParam]);

        $properties = $params->getProperties();
        $this->assertCount(3, $properties);
        $this->assertContains($nameParam, $properties);
        $this->assertContains($ageParam, $properties);
        $this->assertContains($emailParam, $properties);

        // 验证必需参数自动更新
        $required = $params->getRequired();
        $this->assertCount(2, $required);
        $this->assertContains('name', $required);
        $this->assertContains('email', $required);
    }

    /**
     * 测试嵌套参数结构.
     */
    public function testNestedParameters(): void
    {
        // 创建地址参数集
        $streetParam = ToolParameter::string('street', '街道', true);
        $cityParam = ToolParameter::string('city', '城市', true);
        $zipParam = ToolParameter::string('zip', '邮编');

        $addressParams = [$streetParam, $cityParam, $zipParam];
        $requiredFields = ['street', 'city'];

        // 将地址参数转换为对象参数
        $addressParam = ToolParameter::object('address', '地址', $addressParams, $requiredFields, true);

        // 创建用户参数集
        $nameParam = ToolParameter::string('name', '姓名', true);
        $ageParam = ToolParameter::integer('age', '年龄');

        $userParams = new ToolParameters(
            [$nameParam, $ageParam, $addressParam],
            'object',
            '用户信息',
            '用户的详细信息'
        );

        // 验证用户参数集
        $properties = $userParams->getProperties();
        $this->assertCount(3, $properties);

        // 验证必需参数
        $required = $userParams->getRequired();
        $this->assertCount(2, $required);
        $this->assertContains('name', $required);
        $this->assertContains('address', $required);

        // 验证嵌套结构
        $array = $userParams->toArray();
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('address', $array['properties']);
        $this->assertEquals('object', $array['properties']['address']['type']);
        $this->assertArrayHasKey('properties', $array['properties']['address']);
        $this->assertArrayHasKey('street', $array['properties']['address']['properties']);
        $this->assertArrayHasKey('city', $array['properties']['address']['properties']);
        $this->assertArrayHasKey('zip', $array['properties']['address']['properties']);
        $this->assertEquals(['street', 'city'], $array['properties']['address']['required']);
    }

    /**
     * 测试参数结构验证.
     */
    public function testParameterStructure(): void
    {
        // 创建参数定义
        $nameParam = ToolParameter::string('name', '姓名', true);
        $nameParam->setMinLength(2);
        $nameParam->setMaxLength(50);

        $ageParam = ToolParameter::integer('age', '年龄', true);
        $ageParam->setMinimum(18);
        $ageParam->setMaximum(120);

        $emailParam = ToolParameter::string('email', '电子邮件');
        $emailParam->setFormat('email');

        $params = new ToolParameters([$nameParam, $ageParam, $emailParam]);

        // 测试参数结构
        $array = $params->toArray();

        // 验证必需参数
        $this->assertEquals(['name', 'age'], $array['required']);

        // 验证参数属性
        $this->assertArrayHasKey('properties', $array);
        $this->assertArrayHasKey('name', $array['properties']);
        $this->assertArrayHasKey('age', $array['properties']);
        $this->assertArrayHasKey('email', $array['properties']);

        // 验证name参数
        $nameProperty = $array['properties']['name'];
        $this->assertEquals('string', $nameProperty['type']);
        $this->assertEquals('姓名', $nameProperty['description']);
        $this->assertEquals(2, $nameProperty['minLength']);
        $this->assertEquals(50, $nameProperty['maxLength']);

        // 验证age参数
        $ageProperty = $array['properties']['age'];
        $this->assertEquals('integer', $ageProperty['type']);
        $this->assertEquals('年龄', $ageProperty['description']);
        $this->assertEquals(18, $ageProperty['minimum']);
        $this->assertEquals(120, $ageProperty['maximum']);

        // 验证email参数
        $emailProperty = $array['properties']['email'];
        $this->assertEquals('string', $emailProperty['type']);
        $this->assertEquals('电子邮件', $emailProperty['description']);
        $this->assertEquals('email', $emailProperty['format']);
    }
}

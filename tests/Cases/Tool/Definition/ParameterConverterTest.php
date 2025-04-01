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

use Hyperf\Odin\Tool\Definition\ParameterConverter;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;
use InvalidArgumentException;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
class ParameterConverterTest extends ToolBaseTestCase
{
    /**
     * 测试字符串类型转换.
     */
    public function testConvertStringType(): void
    {
        // 各种类型转字符串
        $this->assertEquals('test', ParameterConverter::toString('test'));
        $this->assertEquals('123', ParameterConverter::toString(123));
        $this->assertEquals('123.45', ParameterConverter::toString(123.45));
        $this->assertEquals('true', ParameterConverter::toString(true));
        $this->assertEquals('false', ParameterConverter::toString(false));

        // 复杂类型转字符串
        $array = ['a' => 1, 'b' => 2];
        $this->assertEquals('{"a":1,"b":2}', ParameterConverter::toString($array));

        $obj = new stdClass();
        $obj->property = 'value';
        $this->assertEquals('{"property":"value"}', ParameterConverter::toString($obj));

        // 测试通用转换方法
        $this->assertEquals('test', ParameterConverter::convert('test', 'string'));
        $this->assertEquals('123', ParameterConverter::convert(123, 'string'));
    }

    /**
     * 测试数字类型转换.
     */
    public function testConvertNumberType(): void
    {
        // 各种类型转数字
        $this->assertEquals(123.0, ParameterConverter::toNumber(123));
        $this->assertEquals(123.45, ParameterConverter::toNumber(123.45));
        $this->assertEquals(123.0, ParameterConverter::toNumber('123'));
        $this->assertEquals(123.45, ParameterConverter::toNumber('123.45'));
        $this->assertEquals(1.0, ParameterConverter::toNumber(true));
        $this->assertEquals(0.0, ParameterConverter::toNumber(false));
        $this->assertEquals(1.0, ParameterConverter::toNumber('true'));
        $this->assertEquals(0.0, ParameterConverter::toNumber('false'));

        // 测试无法转换的情况
        $this->expectException(InvalidArgumentException::class);
        ParameterConverter::toNumber('not a number');

        // 测试通用转换方法
        $this->assertEquals(123.45, ParameterConverter::convert('123.45', 'number'));
        $this->assertEquals(123.0, ParameterConverter::convert(123, 'number'));
    }

    /**
     * 测试整数类型转换.
     */
    public function testConvertIntegerType(): void
    {
        // 各种类型转整数
        $this->assertEquals(123, ParameterConverter::toInteger(123));
        $this->assertEquals(123, ParameterConverter::toInteger(123.45));
        $this->assertEquals(123, ParameterConverter::toInteger('123'));
        $this->assertEquals(123, ParameterConverter::toInteger('123.45'));
        $this->assertEquals(1, ParameterConverter::toInteger(true));
        $this->assertEquals(0, ParameterConverter::toInteger(false));
        $this->assertEquals(1, ParameterConverter::toInteger('true'));
        $this->assertEquals(0, ParameterConverter::toInteger('false'));

        // 测试无法转换的情况
        $this->expectException(InvalidArgumentException::class);
        ParameterConverter::toInteger('not an integer');

        // 测试通用转换方法
        $this->assertEquals(123, ParameterConverter::convert('123', 'integer'));
        $this->assertEquals(123, ParameterConverter::convert(123.45, 'integer'));
    }

    /**
     * 测试布尔类型转换.
     */
    public function testConvertBooleanType(): void
    {
        // 各种类型转布尔
        $this->assertTrue(ParameterConverter::toBoolean(true));
        $this->assertFalse(ParameterConverter::toBoolean(false));
        $this->assertTrue(ParameterConverter::toBoolean(1));
        $this->assertFalse(ParameterConverter::toBoolean(0));
        $this->assertTrue(ParameterConverter::toBoolean('true'));
        $this->assertFalse(ParameterConverter::toBoolean('false'));
        $this->assertTrue(ParameterConverter::toBoolean('yes'));
        $this->assertFalse(ParameterConverter::toBoolean('no'));
        $this->assertTrue(ParameterConverter::toBoolean('on'));
        $this->assertFalse(ParameterConverter::toBoolean('off'));
        $this->assertTrue(ParameterConverter::toBoolean('1'));
        $this->assertFalse(ParameterConverter::toBoolean('0'));
        $this->assertFalse(ParameterConverter::toBoolean(''));

        // 非空值默认为 true
        $this->assertTrue(ParameterConverter::toBoolean('any non-empty string'));
        $this->assertTrue(ParameterConverter::toBoolean(['non-empty array']));

        // 测试通用转换方法
        $this->assertTrue(ParameterConverter::convert('true', 'boolean'));
        $this->assertFalse(ParameterConverter::convert('false', 'boolean'));
        $this->assertTrue(ParameterConverter::convert(1, 'boolean'));
    }

    /**
     * 测试数组类型转换.
     */
    public function testConvertArrayType(): void
    {
        // 已经是数组的情况
        $this->assertEquals([1, 2, 3], ParameterConverter::toArray([1, 2, 3]));

        // 字符串转数组
        $this->assertEquals(['a', 'b', 'c'], ParameterConverter::toArray('a,b,c'));

        // JSON 字符串转数组
        $this->assertEquals([1, 2, 3], ParameterConverter::toArray('[1, 2, 3]'));
        $this->assertEquals(['a' => 1, 'b' => 2], ParameterConverter::toArray('{"a": 1, "b": 2}'));

        // 标量值转换为单元素数组
        $this->assertEquals([123], ParameterConverter::toArray(123));
        $this->assertEquals([true], ParameterConverter::toArray(true));

        // 对象转数组
        $obj = new stdClass();
        $obj->a = 1;
        $obj->b = 2;
        $this->assertEquals(['a' => 1, 'b' => 2], ParameterConverter::toArray($obj));

        // 带类型转换的数组
        $schema = ['items' => ['type' => 'integer']];
        $this->assertEquals([1, 2, 3], ParameterConverter::toArray(['1', '2', '3'], $schema));
        $this->assertEquals([1, 2, 3], ParameterConverter::toArray('1,2,3', $schema));

        // 测试通用转换方法
        $this->assertEquals([1, 2, 3], ParameterConverter::convert('[1,2,3]', 'array'));
        $this->assertEquals(['a', 'b'], ParameterConverter::convert('a,b', 'array'));
    }

    /**
     * 测试对象类型转换.
     */
    public function testConvertObjectType(): void
    {
        // 关联数组转对象
        $array = ['name' => '张三', 'age' => 30];
        $this->assertEquals($array, ParameterConverter::toObject($array));

        // JSON 字符串转对象
        $jsonStr = '{"name": "张三", "age": 30}';
        $this->assertEquals(['name' => '张三', 'age' => 30], ParameterConverter::toObject($jsonStr));

        // 对象转对象（实际上是转为关联数组）
        $obj = new stdClass();
        $obj->name = '张三';
        $obj->age = 30;
        $this->assertEquals(['name' => '张三', 'age' => 30], ParameterConverter::toObject($obj));

        // 标量值转为简单对象
        $this->assertEquals(['value' => 'test'], ParameterConverter::toObject('test'));

        // 带属性类型转换的对象
        $schema = [
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
        ];
        $data = ['name' => '李四', 'age' => '25', 'active' => 'true'];
        $expected = ['name' => '李四', 'age' => 25, 'active' => true];
        $this->assertEquals($expected, ParameterConverter::toObject($data, $schema));

        // 测试通用转换方法
        $this->assertEquals(['name' => '张三', 'age' => 30], ParameterConverter::convert('{"name": "张三", "age": 30}', 'object'));
    }

    /**
     * 测试复杂嵌套类型转换.
     */
    public function testConvertNestedTypes(): void
    {
        // 嵌套结构
        $schema = [
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'contact' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                    ],
                ],
                'skills' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];

        $data = [
            'name' => '王五',
            'age' => '35',
            'contact' => [
                'email' => 'wangwu@example.com',
                'phone' => '13800138000',
            ],
            'skills' => '编程,设计,沟通',
        ];

        $converted = ParameterConverter::toObject($data, $schema);

        $this->assertEquals('王五', $converted['name']);
        $this->assertIsInt($converted['age']);
        $this->assertEquals(35, $converted['age']);
        $this->assertIsArray($converted['contact']);
        $this->assertEquals('wangwu@example.com', $converted['contact']['email']);
        $this->assertIsArray($converted['skills']);
        $this->assertEquals(['编程', '设计', '沟通'], $converted['skills']);
    }

    /**
     * 测试无效输入处理.
     */
    public function testHandleInvalidInput(): void
    {
        // 数字转换错误测试
        try {
            ParameterConverter::toNumber('not a number');
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot convert value to number', $e->getMessage());
        }

        // 整数转换错误测试
        try {
            ParameterConverter::toInteger('not an integer');
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot convert value to integer', $e->getMessage());
        }

        // 数组转换错误测试（任意标量都可以转为数组，所以使用无法处理的资源类型）
        $resource = fopen('php://memory', 'r');
        try {
            ParameterConverter::toArray($resource);
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot convert value to array', $e->getMessage());
        }
        fclose($resource);

        // 对象转换错误测试（同上）
        $resource = fopen('php://memory', 'r');
        try {
            ParameterConverter::toObject($resource);
            $this->fail('Expected exception was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Cannot convert value to object', $e->getMessage());
        }
        fclose($resource);
    }

    /**
     * 测试特殊格式转换（日期、时间等）.
     */
    public function testConvertSpecialFormats(): void
    {
        // 测试使用 schema 中的 format 信息进行类型转换
        // 目前看源码中没有直接支持日期格式转换，但可以测试通用转换行为

        // 转换为字符串，不特别处理 format
        $schema = ['type' => 'string', 'format' => 'date'];
        $this->assertEquals('2023-06-15', ParameterConverter::convert('2023-06-15', 'string', $schema));

        // 如果扩展实现日期格式支持，可以添加更多测试

        // 测试多类型字段处理
        $this->assertEquals(123, ParameterConverter::convert('123', ['integer', 'string']));
        $this->assertEquals('abc', ParameterConverter::convert('abc', ['integer', 'string']));
        $this->assertEquals(null, ParameterConverter::convert(null, ['string', 'null']));

        // 仅有 null 类型的特殊情况
        $this->assertEquals('test', ParameterConverter::convert('test', ['null'])); // 保持原值
        $this->assertEquals(null, ParameterConverter::convert(null, ['null']));
    }
}

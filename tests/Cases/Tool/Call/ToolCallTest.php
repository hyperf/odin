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

namespace HyperfTest\Odin\Cases\Tool\Call;

use Hyperf\Odin\Api\Response\ToolCall;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * @internal
 * @coversNothing
 */
class ToolCallTest extends ToolBaseTestCase
{
    /**
     * 测试基本构造函数.
     */
    public function testBasicConstruction(): void
    {
        $toolCall = new ToolCall(
            name: 'get_weather',
            arguments: ['location' => '北京', 'unit' => 'celsius'],
            id: 'call_12345',
            type: 'function'
        );

        $this->assertEquals('get_weather', $toolCall->getName());
        $this->assertEquals(['location' => '北京', 'unit' => 'celsius'], $toolCall->getArguments());
        $this->assertEquals('call_12345', $toolCall->getId());
        $this->assertEquals('function', $toolCall->getType());
    }

    /**
     * 测试从API响应解析工具调用.
     */
    public function testFromArrayMethod(): void
    {
        // 加载模拟数据
        $toolCallsData = require $this->getFixturePath('Tool/tool_call_responses.php');
        $basicToolCall = $toolCallsData['basic_tool_call']['choices'][0]['message']['tool_calls'];
        $multipleToolCall = $toolCallsData['multiple_tool_calls']['choices'][0]['message']['tool_calls'];

        // 测试单个工具调用解析
        $toolCalls = ToolCall::fromArray($basicToolCall);
        $this->assertCount(1, $toolCalls);
        $this->assertInstanceOf(ToolCall::class, $toolCalls[0]);
        $this->assertEquals('get_weather', $toolCalls[0]->getName());
        $this->assertEquals(['location' => '北京', 'unit' => 'celsius'], $toolCalls[0]->getArguments());
        $this->assertEquals('call_01234567890abcdef', $toolCalls[0]->getId());

        // 测试多个工具调用解析
        $toolCalls = ToolCall::fromArray($multipleToolCall);
        $this->assertCount(2, $toolCalls);
        $this->assertEquals('get_user_profile', $toolCalls[0]->getName());
        $this->assertEquals('get_user_orders', $toolCalls[1]->getName());
        $this->assertEquals(12345, $toolCalls[0]->getArguments()['user_id']);
        $this->assertEquals(5, $toolCalls[1]->getArguments()['limit']);
    }

    /**
     * 测试无效数据处理.
     */
    public function testInvalidDataHandling(): void
    {
        // 测试缺少function字段
        $invalidData = [
            [
                'id' => 'call_invalid',
                'type' => 'function',
                // 缺少function字段
            ],
        ];
        $result = ToolCall::fromArray($invalidData);
        $this->assertEmpty($result);

        // 测试缺少arguments字段
        $invalidData = [
            [
                'id' => 'call_invalid',
                'type' => 'function',
                'function' => [
                    'name' => 'test_function',
                    // 缺少arguments字段
                ],
            ],
        ];
        $result = ToolCall::fromArray($invalidData);
        $this->assertEmpty($result);

        // 测试非JSON格式的arguments
        $invalidData = [
            [
                'id' => 'call_invalid',
                'type' => 'function',
                'function' => [
                    'name' => 'test_function',
                    'arguments' => 'this is not json',
                ],
            ],
        ];
        $result = ToolCall::fromArray($invalidData);
        $this->assertCount(1, $result);
        $this->assertEmpty($result[0]->getArguments());
    }

    /**
     * 测试转换为数组格式.
     */
    public function testToArrayMethod(): void
    {
        $toolCall = new ToolCall(
            name: 'calculate',
            arguments: ['num1' => 10, 'num2' => 20, 'operation' => 'add'],
            id: 'call_calc123',
            type: 'function'
        );

        $array = $toolCall->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('call_calc123', $array['id']);
        $this->assertEquals('function', $array['type']);
        $this->assertEquals('calculate', $array['function']['name']);

        // 验证序列化后的参数
        $expectedArgs = json_encode(['num1' => 10, 'num2' => 20, 'operation' => 'add'], JSON_UNESCAPED_UNICODE);
        $this->assertEquals($expectedArgs, $array['function']['arguments']);
    }

    /**
     * 测试参数的序列化和反序列化.
     */
    public function testArgumentsSerialization(): void
    {
        $arguments = [
            'complex' => [
                'nested' => [
                    'value' => '嵌套值',
                ],
                'array' => [1, 2, 3],
            ],
            'simple' => '简单值',
        ];

        $toolCall = new ToolCall(
            name: 'test_serialization',
            arguments: $arguments,
            id: 'call_serial123'
        );

        // 测试 getSerializedArguments 方法
        $serialized = $toolCall->getSerializedArguments();
        $this->assertIsString($serialized);

        // 反序列化并验证结果
        $deserialized = json_decode($serialized, true);
        $this->assertEquals($arguments, $deserialized);

        // 验证中文字符正确处理
        $this->assertStringContainsString('嵌套值', $serialized);
        $this->assertStringContainsString('简单值', $serialized);
    }

    /**
     * 测试 getter 和 setter 方法.
     */
    public function testGettersAndSetters(): void
    {
        $toolCall = new ToolCall(
            name: 'original_name',
            arguments: ['original' => 'value'],
            id: 'original_id',
            type: 'original_type'
        );

        // 测试 setter 方法
        $toolCall->setName('new_name');
        $toolCall->setArguments(['new' => 'value']);
        $toolCall->setId('new_id');
        $toolCall->setType('new_type');

        // 测试 getter 方法
        $this->assertEquals('new_name', $toolCall->getName());
        $this->assertEquals(['new' => 'value'], $toolCall->getArguments());
        $this->assertEquals('new_id', $toolCall->getId());
        $this->assertEquals('new_type', $toolCall->getType());
    }

    /**
     * 测试不同类型的工具调用.
     */
    public function testDifferentToolTypes(): void
    {
        // 默认类型是 'function'
        $toolCall = new ToolCall(
            name: 'default_type_test',
            arguments: [],
            id: 'call_default'
        );
        $this->assertEquals('function', $toolCall->getType());

        // 自定义类型
        $toolCall = new ToolCall(
            name: 'custom_type_test',
            arguments: [],
            id: 'call_custom',
            type: 'custom_type'
        );
        $this->assertEquals('custom_type', $toolCall->getType());
    }

    /**
     * 测试流式参数追加功能.
     */
    public function testStreamArgumentsAppending(): void
    {
        $toolCall = new ToolCall(
            name: 'stream_test',
            arguments: [],
            id: 'call_stream',
            type: 'function',
            streamArguments: '{"partial":'
        );

        // 初始状态
        $this->assertEquals('{"partial":', $toolCall->getStreamArguments());
        $this->assertEquals('{"partial":', $toolCall->getOriginalArguments());

        // 追加参数
        $toolCall->appendStreamArguments('"data"}');

        // 追加后状态
        $this->assertEquals('{"partial":"data"}', $toolCall->getStreamArguments());
        $this->assertEquals('{"partial":"data"}', $toolCall->getOriginalArguments());

        // 验证参数解析
        $this->assertEquals(['partial' => 'data'], $toolCall->getArguments());
    }

    /**
     * 获取测试固定数据路径.
     */
    private function getFixturePath(string $path): string
    {
        return dirname(__DIR__, 3) . '/Fixtures/' . $path;
    }
}

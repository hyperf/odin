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

namespace HyperfTest\Odin\Cases\Message;

use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\ToolMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 工具消息类测试.
 * @internal
 */
#[CoversClass(ToolMessage::class)]
class ToolMessageTest extends AbstractTestCase
{
    /**
     * 测试工具消息的角色.
     */
    public function testRole()
    {
        $message = new ToolMessage('工具返回结果', 'tool-123');
        // 工具消息的角色应该是 tool
        $this->assertSame(Role::Tool, $message->getRole());
    }

    /**
     * 测试基本内容.
     */
    public function testBasicContent()
    {
        $content = '工具返回结果';
        $toolCallId = 'tool-123';

        $message = new ToolMessage($content, $toolCallId);

        // 测试基本内容和 ID
        $this->assertSame($content, $message->getContent());
        $this->assertSame($toolCallId, $message->getToolCallId());

        // 测试可选参数的默认值
        $this->assertNull($message->getName());
        $this->assertNull($message->getArguments());
    }

    /**
     * 测试名称和参数.
     */
    public function testNameAndArguments()
    {
        $content = '北京，今天天气晴朗，气温15-25摄氏度';
        $toolCallId = 'tool-456';
        $name = 'get_weather';
        $arguments = ['city' => '北京', 'date' => 'today'];

        $message = new ToolMessage($content, $toolCallId, $name, $arguments);

        // 测试所有属性
        $this->assertSame($content, $message->getContent());
        $this->assertSame($toolCallId, $message->getToolCallId());
        $this->assertSame($name, $message->getName());
        $this->assertSame($arguments, $message->getArguments());

        // 测试设置和获取
        $message->setToolCallId('new-tool-id');
        $message->setName('new_function_name');
        $message->setArguments(['param' => 'new_value']);
        $message->setContent('新的内容');

        $this->assertSame('new-tool-id', $message->getToolCallId());
        $this->assertSame('new_function_name', $message->getName());
        $this->assertSame(['param' => 'new_value'], $message->getArguments());
        $this->assertSame('新的内容', $message->getContent());
    }

    /**
     * 测试函数静态方法创建.
     */
    public function testFunctionStaticCreation()
    {
        $content = '函数执行结果：42';
        $toolCallId = 'func-789';
        $name = 'calculate';
        $arguments = ['x' => 10, 'y' => 20];

        $message = ToolMessage::function($content, $toolCallId, $name, $arguments);

        // 测试角色为 function
        $this->assertSame(Role::Tool, $message->getRole());

        // 测试其他属性
        $this->assertSame($content, $message->getContent());
        $this->assertSame($toolCallId, $message->getToolCallId());
        $this->assertSame($name, $message->getName());
        $this->assertSame($arguments, $message->getArguments());
    }

    /**
     * 测试 toArray 方法.
     */
    public function testToArray()
    {
        // 测试基本工具消息
        $message = new ToolMessage('工具结果', 'tool-123');
        $message->setIdentifier('tool-id-123');

        $array = $message->toArray();

        // 检查基本结构
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('tool_call_id', $array);
        $this->assertArrayNotHasKey('identifier', $array);

        // 检查值
        $this->assertSame(Role::Tool->value, $array['role']);
        $this->assertSame('工具结果', $array['content']);
        $this->assertSame('tool-123', $array['tool_call_id']);

        // 测试附加名称和参数
        $message = new ToolMessage('工具结果', 'tool-123', 'get_data', ['id' => 1]);
        $array = $message->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('arguments', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertSame('get_data', $array['name']);
        $this->assertSame(['id' => 1], $array['arguments']);

        // 测试函数消息
        $functionMessage = ToolMessage::function('函数结果', 'func-456', 'calculate', ['x' => 10]);
        $functionArray = $functionMessage->toArray();
        $this->assertSame(Role::Tool->value, $functionArray['role']);
    }

    /**
     * 测试 fromArray 方法.
     */
    public function testFromArray()
    {
        // 测试创建工具消息
        $data = [
            'role' => 'tool',
            'content' => '工具结果',
            'tool_call_id' => 'tool-123',
            'name' => 'get_data',
            'arguments' => ['id' => 1],
        ];

        $message = ToolMessage::fromArray($data);

        $this->assertSame(Role::Tool, $message->getRole());
        $this->assertSame('工具结果', $message->getContent());
        $this->assertSame('tool-123', $message->getToolCallId());
        $this->assertSame('get_data', $message->getName());
        $this->assertSame(['id' => 1], $message->getArguments());
        $this->assertSame('', $message->getIdentifier());

        // 手动设置标识符
        $message->setIdentifier('msg-id-789');
        $this->assertSame('msg-id-789', $message->getIdentifier());

        // 测试创建函数消息
        $functionData = [
            'role' => 'function',
            'content' => '函数结果',
            'tool_call_id' => 'func-456',
            'name' => 'calculate',
            'arguments' => ['x' => 10],
        ];

        $functionMessage = ToolMessage::fromArray($functionData);

        $this->assertSame(Role::Tool, $functionMessage->getRole());
        $this->assertSame('函数结果', $functionMessage->getContent());
        $this->assertSame('func-456', $functionMessage->getToolCallId());
        $this->assertSame('calculate', $functionMessage->getName());
        $this->assertSame(['x' => 10], $functionMessage->getArguments());
    }
}

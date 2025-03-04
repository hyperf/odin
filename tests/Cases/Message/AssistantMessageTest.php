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

use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\Role;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * 助手消息类测试.
 * @internal
 */
#[CoversClass(AssistantMessage::class)]
class AssistantMessageTest extends AbstractTestCase
{
    /**
     * 测试助手消息的角色.
     */
    public function testRole()
    {
        $message = new AssistantMessage('助手回复');
        $this->assertSame(Role::Assistant, $message->getRole());
    }

    /**
     * 测试基本消息内容.
     */
    public function testBasicContent()
    {
        $message = new AssistantMessage('助手回复内容');
        $this->assertSame('助手回复内容', $message->getContent());

        // 测试 toArray
        $array = $message->toArray();
        $this->assertArrayHasKey('role', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertSame(Role::Assistant->value, $array['role']);
        $this->assertSame('助手回复内容', $array['content']);
    }

    /**
     * 测试带有工具调用的助手消息.
     */
    public function testWithToolCalls()
    {
        // 构造工具调用
        $toolCall = $this->createMock(ToolCall::class);
        $toolCall->method('toArray')->willReturn([
            'id' => 'tool-123',
            'type' => 'function',
            'function' => [
                'name' => 'get_weather',
                'arguments' => json_encode(['city' => '北京']),
            ],
        ]);

        $message = new AssistantMessage('需要查询天气', [$toolCall]);

        // 测试工具调用相关方法
        $this->assertTrue($message->hasToolCalls());
        $this->assertCount(1, $message->getToolCalls());

        // 测试设置工具调用
        $newToolCall = $this->createMock(ToolCall::class);
        $newToolCall->method('toArray')->willReturn([
            'id' => 'tool-456',
            'type' => 'function',
            'function' => [
                'name' => 'get_location',
                'arguments' => json_encode(['ip' => '127.0.0.1']),
            ],
        ]);

        $message->setToolCalls([$newToolCall]);
        $this->assertCount(1, $message->getToolCalls());

        // 测试 toArray 包含工具调用
        $array = $message->toArray();
        $this->assertArrayHasKey('tool_calls', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertIsArray($array['tool_calls']);
        $this->assertCount(1, $array['tool_calls']);
        $this->assertSame('tool-456', $array['tool_calls'][0]['id']);
    }

    /**
     * 测试带有推理内容的助手消息.
     */
    public function testWithReasoningContent()
    {
        $message = new AssistantMessage('最终回复', [], '思考过程：首先分析问题...');

        // 测试推理内容相关方法
        $this->assertTrue($message->hasReasoningContent());
        $this->assertSame('思考过程：首先分析问题...', $message->getReasoningContent());

        // 测试设置推理内容
        $message->setReasoningContent('新的思考过程：重新分析...');
        $this->assertSame('新的思考过程：重新分析...', $message->getReasoningContent());

        // 测试 toArray 包含推理内容
        $array = $message->toArray();
        $this->assertArrayHasKey('reasoning_content', $array);
        $this->assertArrayNotHasKey('identifier', $array);
        $this->assertSame('新的思考过程：重新分析...', $array['reasoning_content']);
    }

    /**
     * 测试从数组创建助手消息.
     */
    public function testFromArray()
    {
        // 准备数据
        $array = [
            'content' => '助手消息内容',
            'reasoning_content' => '推理过程',
            'tool_calls' => [
                [
                    'id' => 'tool-789',
                    'type' => 'function',
                    'function' => [
                        'name' => 'test_function',
                        'arguments' => json_encode(['param' => 'value']),
                    ],
                ],
            ],
        ];

        // 简化测试，不再尝试 mock 静态方法
        $message = AssistantMessage::fromArray($array);

        // 基本断言
        $this->assertInstanceOf(AssistantMessage::class, $message);
        $this->assertSame('助手消息内容', $message->getContent());
        $this->assertSame('推理过程', $message->getReasoningContent());
        $this->assertSame('', $message->getIdentifier());

        // 手动设置标识符
        $message->setIdentifier('assistant-123');
        $this->assertSame('assistant-123', $message->getIdentifier());

        // 确认 toolCalls 属性被设置
        $this->assertTrue($message->hasToolCalls());
    }
}

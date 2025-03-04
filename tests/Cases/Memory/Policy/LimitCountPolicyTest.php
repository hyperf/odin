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

namespace HyperfTest\Odin\Cases\Memory\Policy;

use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 * @coversClass(LimitCountPolicy::class)
 * @coversNothing
 */
class LimitCountPolicyTest extends AbstractTestCase
{
    public function testProcessWithEmptyMessages()
    {
        $policy = new LimitCountPolicy();
        $result = $policy->process([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProcessWithMessagesUnderLimit()
    {
        $policy = new LimitCountPolicy();

        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
            new UserMessage('消息3'),
        ];

        $result = $policy->process($messages);

        $this->assertCount(3, $result);
        $this->assertSame($messages, $result);
    }

    public function testProcessWithMessagesOverLimit()
    {
        $policy = new LimitCountPolicy(['max_count' => 3]);

        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
            new UserMessage('消息3'),
            new UserMessage('消息4'),
            new UserMessage('消息5'),
        ];

        $result = $policy->process($messages);

        $this->assertCount(3, $result);
        // 验证保留了第一条用户消息以及最新的消息
        $this->assertSame('消息1', $result[0]->getContent());
        $this->assertSame('消息4', $result[1]->getContent());
        $this->assertSame('消息5', $result[2]->getContent());
    }

    public function testConfigureMethod()
    {
        $policy = new LimitCountPolicy();
        $policy->configure(['max_count' => 5]);

        $messages = [];
        for ($i = 1; $i <= 10; ++$i) {
            $messages[] = new UserMessage("消息{$i}");
        }

        $result = $policy->process($messages);

        $this->assertCount(5, $result);
        // 验证保留了第一条用户消息以及最新的消息
        $this->assertSame('消息1', $result[0]->getContent());
        for ($i = 1; $i < 5; ++$i) {
            $this->assertSame('消息' . ($i + 6), $result[$i]->getContent());
        }
    }

    public function testDefaultOptions()
    {
        $policy = new LimitCountPolicy();

        $messages = [];
        for ($i = 1; $i <= 15; ++$i) {
            $messages[] = new UserMessage("消息{$i}");
        }

        $result = $policy->process($messages);

        // 默认 max_count 是 10
        $this->assertCount(10, $result);

        // 验证保留了第一条用户消息以及最新的消息
        $this->assertSame('消息1', $result[0]->getContent());
        for ($i = 1; $i < 10; ++$i) {
            $this->assertSame('消息' . ($i + 6), $result[$i]->getContent());
        }
    }

    /**
     * 测试优先抛弃 Assistant 工具使用消息和 Tool 回复消息.
     */
    public function testPriorityRemovalOfToolRelatedMessages()
    {
        $policy = new LimitCountPolicy(['max_count' => 5]);

        // 创建工具调用
        $toolCall = new ToolCall(
            name: 'get_weather',
            arguments: ['city' => '北京'],
            id: 'tool-123',
            type: 'function'
        );

        // 构建消息序列：包含普通消息和工具相关消息
        $messages = [
            new SystemMessage('系统提示'),
            new UserMessage('用户问题1'),
            new AssistantMessage('助手回复，包含工具调用', [$toolCall]),
            new ToolMessage('北京天气晴朗', 'tool-123', 'get_weather', ['city' => '北京']),
            new AssistantMessage('助手回复1'),
            new UserMessage('用户问题2'),
            new AssistantMessage('助手回复2'),
        ];

        $result = $policy->process($messages);

        // 应该保留5条消息，且优先移除了工具相关消息
        $this->assertCount(5, $result);
        $this->assertSame('用户问题1', $result[1]->getContent());
        $this->assertSame('助手回复1', $result[2]->getContent());
        $this->assertSame('用户问题2', $result[3]->getContent());
        $this->assertSame('助手回复2', $result[4]->getContent());
    }

    /**
     * 测试即使在消息数量超过限制时也会保留第一条用户消息.
     */
    public function testPreservationOfFirstUserMessage()
    {
        $policy = new LimitCountPolicy(['max_count' => 4]);

        // 创建一系列消息，确保第一条是用户消息
        $messages = [
            new UserMessage('第一条用户消息'), // 这条应该被保留
            new AssistantMessage('助手回复1'),
            new UserMessage('用户问题2'),
            new AssistantMessage('助手回复2'),
            new UserMessage('用户问题3'),
            new AssistantMessage('助手回复3'),
            new UserMessage('用户问题4'),
            new AssistantMessage('助手回复4'),
        ];

        $result = $policy->process($messages);

        // 应该保留4条消息，包括第一条用户消息和最新的3条消息
        $this->assertCount(4, $result);

        // 验证第一条消息是第一条用户消息
        $this->assertSame('第一条用户消息', $result[0]->getContent());

        // 验证保留了最新的消息
        $this->assertSame('用户问题4', $result[2]->getContent());
        $this->assertSame('助手回复4', $result[3]->getContent());
    }

    /**
     * 测试当第一条用户消息已经存在于保留的最新消息中时的情况.
     */
    public function testFirstUserMessageAlreadyInLatestMessages()
    {
        $policy = new LimitCountPolicy(['max_count' => 3]);

        // 创建一系列消息，第一条用户消息恰好也是最新的消息之一
        $messages = [
            new UserMessage('第一条用户消息'), // 这条应该被保留
            new AssistantMessage('助手回复1'),
            new UserMessage('用户问题2'),
            new AssistantMessage('助手回复2'),
        ];

        $result = $policy->process($messages);

        // 应该保留3条消息，不会重复添加第一条用户消息
        $this->assertCount(3, $result);

        // 验证结果包含正确的消息
        $this->assertSame('第一条用户消息', $result[0]->getContent());
        $this->assertSame('用户问题2', $result[1]->getContent());
        $this->assertSame('助手回复2', $result[2]->getContent());
    }
}

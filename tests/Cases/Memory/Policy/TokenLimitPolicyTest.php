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

use Hyperf\Odin\Memory\Policy\TokenLimitPolicy;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;

/**
 * @internal
 * @coversClass(TokenLimitPolicy::class)
 * @coversNothing
 */
class TokenLimitPolicyTest extends AbstractTestCase
{
    public function testProcessWithEmptyMessages()
    {
        $policy = new TokenLimitPolicy();
        $result = $policy->process([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProcessWithMessagesUnderTokenLimit()
    {
        $policy = new TokenLimitPolicy(['max_tokens' => 100]);

        // 假设每个消息内容约 10 个 token（按照 token_ratio = 3.5，大约 35 个字符）
        $messages = [
            new UserMessage('消息内容1，这是一条短消息'),
            new UserMessage('消息内容2，这也是一条短消息'),
            new UserMessage('消息内容3，这也是很短的消息'),
        ];

        $result = $policy->process($messages);

        // 所有消息都应该保留
        $this->assertCount(3, $result);
        $this->assertSame($messages, $result);
    }

    public function testProcessWithMessagesOverTokenLimit()
    {
        $policy = new TokenLimitPolicy(['max_tokens' => 100, 'token_ratio' => 3.5]);

        // 创建一些消息，其中有长有短
        $messages = [
            new UserMessage('短消息1'), // ~3 tokens
            new UserMessage('这是一条稍微长一点的消息，包含更多的内容来增加 token 数量'), // ~20 tokens
            new UserMessage('短消息2'), // ~3 tokens
            // 这条消息非常长，估计超过 100 tokens
            new UserMessage(str_repeat('这是一条非常长的消息，重复多次来确保超过 token 限制。', 20)),
            new UserMessage('最后一条短消息'), // ~5 tokens
        ];

        $result = $policy->process($messages);

        // 由于第四条消息非常长，应该只包含最后两条消息（长消息和最后的短消息）
        // 或者如果长消息自身就超过了限制，则可能只包含最后一条消息
        $this->assertLessThanOrEqual(2, count($result));

        // 验证最后一条消息始终被保留
        if (count($result) > 0) {
            $lastMessage = $result[count($result) - 1];
            $this->assertSame('最后一条短消息', $lastMessage->getContent());
        }
    }

    public function testConfigureMethod()
    {
        $policy = new TokenLimitPolicy();
        $policy->configure(['max_tokens' => 50, 'token_ratio' => 4.0]);

        // 创建一系列短消息，每条约 10-15 tokens
        $messages = [];
        for ($i = 1; $i <= 10; ++$i) {
            $messages[] = new UserMessage("这是第 {$i} 条测试消息，用于测试 Token 限制策略的效果和配置方法");
        }

        $result = $policy->process($messages);

        // 根据配置，应该只保留最后几条消息
        $this->assertLessThan(count($messages), count($result));

        // 验证保留的是最新的消息
        if (count($result) > 0) {
            $lastMessage = $result[count($result) - 1];
            $this->assertStringContainsString('10', $lastMessage->getContent());
        }
    }

    public function testDefaultOptions()
    {
        $policy = new TokenLimitPolicy();

        $reflectionClass = new ReflectionClass($policy);
        $method = $reflectionClass->getMethod('getDefaultOptions');
        $method->setAccessible(true);
        $defaultOptions = $method->invoke($policy);

        $this->assertArrayHasKey('max_tokens', $defaultOptions);
        $this->assertArrayHasKey('token_ratio', $defaultOptions);
        $this->assertEquals(4000, $defaultOptions['max_tokens']);
        $this->assertEquals(3.5, $defaultOptions['token_ratio']);
    }
}

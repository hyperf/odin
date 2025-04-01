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

use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Memory\Policy\CompositePolicy;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 * @coversClass(CompositePolicy::class)
 * @coversNothing
 */
class CompositePolicyTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testProcessWithNoPolicies()
    {
        $policy = new CompositePolicy();

        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
        ];

        $result = $policy->process($messages);

        // 没有子策略，应该返回原始消息
        $this->assertSame($messages, $result);
    }

    public function testAddPolicy()
    {
        $policy = new CompositePolicy();
        $mockPolicy = Mockery::mock(PolicyInterface::class);

        $result = $policy->addPolicy($mockPolicy);

        $this->assertSame($policy, $result);
    }

    public function testProcessWithSinglePolicy()
    {
        $policy = new CompositePolicy();

        $messages = [
            new UserMessage('消息1'),
            new UserMessage('消息2'),
            new UserMessage('消息3'),
            new UserMessage('消息4'),
            new UserMessage('消息5'),
        ];

        // 添加一个限制消息数量为 3 的策略
        $limitPolicy = new LimitCountPolicy(['max_count' => 3]);
        $policy->addPolicy($limitPolicy);

        $result = $policy->process($messages);

        // 应该保留第一条用户消息和最新的两条消息
        $this->assertCount(3, $result);
        $this->assertSame('消息1', $result[0]->getContent());
        $this->assertSame('消息4', $result[1]->getContent());
        $this->assertSame('消息5', $result[2]->getContent());
    }

    public function testProcessWithMultiplePolicies()
    {
        $policy = new CompositePolicy();

        // 创建 10 条消息
        $messages = [];
        for ($i = 1; $i <= 10; ++$i) {
            $messages[] = new UserMessage("消息{$i}");
        }

        // 创建两个模拟策略
        $mockPolicy1 = Mockery::mock(PolicyInterface::class);
        $mockPolicy1->shouldReceive('process')
            ->once()
            ->with($messages)
            ->andReturn(array_slice($messages, 2, 6)); // 返回消息3-8

        $mockPolicy2 = Mockery::mock(PolicyInterface::class);
        $mockPolicy2->shouldReceive('process')
            ->once()
            ->with(array_slice($messages, 2, 6))
            ->andReturn(array_slice($messages, 4, 2)); // 进一步过滤为消息5-6

        // 添加策略到组合中
        $policy->addPolicy($mockPolicy1)
            ->addPolicy($mockPolicy2);

        $result = $policy->process($messages);

        // 验证最终结果
        $this->assertCount(2, $result);
        $this->assertSame('消息5', $result[0]->getContent());
        $this->assertSame('消息6', $result[1]->getContent());
    }

    public function testConfigureMethodDoesNotAffectChildPolicies()
    {
        $policy = new CompositePolicy();

        $mockPolicy = Mockery::mock(PolicyInterface::class);
        $mockPolicy->shouldReceive('process')->andReturnUsing(function ($messages) {
            return $messages;
        });

        $policy->addPolicy($mockPolicy);
        $policy->configure(['some_option' => 'value']);

        $messages = [new UserMessage('测试消息')];
        $result = $policy->process($messages);

        $this->assertSame($messages, $result);
    }
}

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

namespace Hyperf\Odin\Memory\Policy;

use Hyperf\Odin\Contract\Memory\PolicyInterface;
use Hyperf\Odin\Message\AbstractMessage;

/**
 * 组合策略.
 *
 * 允许按顺序组合多个策略，依次应用
 */
class CompositePolicy extends AbstractPolicy
{
    /**
     * 策略列表.
     *
     * @var PolicyInterface[]
     */
    protected array $policies = [];

    /**
     * 添加策略到组合中.
     *
     * @param PolicyInterface $policy 要添加的策略
     */
    public function addPolicy(PolicyInterface $policy): self
    {
        $this->policies[] = $policy;
        return $this;
    }

    /**
     * 处理消息列表，按顺序应用所有策略.
     *
     * @param AbstractMessage[] $messages 原始消息列表
     * @return AbstractMessage[] 处理后的消息列表
     */
    public function process(array $messages): array
    {
        $result = $messages;

        foreach ($this->policies as $policy) {
            $result = $policy->process($result);
        }

        return $result;
    }

    /**
     * 获取默认配置选项.
     *
     * @return array 默认配置选项
     */
    protected function getDefaultOptions(): array
    {
        return [];
    }
}

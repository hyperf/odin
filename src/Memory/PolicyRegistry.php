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

namespace Hyperf\Odin\Memory;

use Hyperf\Odin\Contract\Memory\PolicyInterface;

/**
 * 策略注册表.
 *
 * 管理和注册可用的记忆策略
 */
class PolicyRegistry
{
    /**
     * 注册的策略实例.
     *
     * @var array<string, PolicyInterface>
     */
    private array $policies = [];

    /**
     * 注册策略.
     *
     * @param string $name 策略名称
     * @param PolicyInterface $policy 策略实例
     * @return self 支持链式调用
     */
    public function register(string $name, PolicyInterface $policy): self
    {
        $this->policies[$name] = $policy;
        return $this;
    }

    /**
     * 获取策略.
     *
     * @param string $name 策略名称
     * @return null|PolicyInterface 策略实例或null
     */
    public function get(string $name): ?PolicyInterface
    {
        return $this->policies[$name] ?? null;
    }

    /**
     * 策略是否存在.
     *
     * @param string $name 策略名称
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return isset($this->policies[$name]);
    }

    /**
     * 获取所有策略.
     *
     * @return array<string, PolicyInterface> 所有策略
     */
    public function all(): array
    {
        return $this->policies;
    }
}

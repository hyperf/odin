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

namespace Hyperf\Odin\Agent\Tool;

// 导入类型声明支持
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Coroutine\Parallel;
use Hyperf\Engine\Coroutine;
use Throwable;

use function Hyperf\Config\config;

class ToolExecutor
{
    /**
     * 待执行的工具回调列表.
     */
    private array $tools = [];

    /**
     * 是否并行执行.
     */
    private bool $parallel = true;

    /**
     * 添加工具回调到执行列表.
     *
     * @param callable $tool 工具回调函数
     * @return self 支持链式调用
     */
    public function add(callable $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * 设置是否并行执行工具.
     *
     * @param bool $parallel 是否并行
     * @return self 支持链式调用
     */
    public function setParallel(bool $parallel): self
    {
        $this->parallel = $parallel;
        return $this;
    }

    /**
     * 执行所有添加的工具回调.
     *
     * @return array 执行结果数组
     */
    public function run(): array
    {
        if (empty($this->tools)) {
            return [];
        }

        $parallel = $this->parallel;
        $results = [];
        // 如果只有一个工具，直接顺序执行即可
        if (count($this->tools) === 1) {
            $parallel = false;
        }

        // 尝试使用 Parallel 特性进行并行执行
        if ($parallel && $this->isParallelAvailable()) {
            $fromCoroutineId = $this->getCurrentCoroutineId();
            $parallel = $this->createParallel();
            if ($parallel) {
                foreach ($this->tools as $index => $tool) {
                    $parallel->add(function () use ($fromCoroutineId, $tool) {
                        $this->copyContext($fromCoroutineId);
                        return $this->executeToolSafely($tool);
                    }, (string) $index);
                }
                $results = $parallel->wait();
                ksort($results);
            }
        } else {
            // 顺序执行
            foreach ($this->tools as $index => $tool) {
                $results[$index] = $this->executeToolSafely($tool);
            }
        }

        return $results;
    }

    /**
     * 安全执行工具回调，捕获异常.
     *
     * @param callable $tool 工具回调函数
     * @return mixed 执行结果
     */
    private function executeToolSafely(callable $tool): mixed
    {
        try {
            return call_user_func($tool);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 检查 Parallel 特性是否可用.
     *
     * @return bool 是否支持 Parallel
     */
    private function isParallelAvailable(): bool
    {
        if (class_exists(Swoole\Coroutine\Parallel::class)) {
            return true;
        }

        return class_exists(Parallel::class);
    }

    /**
     * 创建 Parallel 实例.
     *
     * @return null|Parallel Parallel 实例或 null
     */
    private function createParallel(): ?Parallel
    {
        if (class_exists(Parallel::class)) {
            return new Parallel(20);
        }

        return null;
    }

    private function getCurrentCoroutineId(): int
    {
        if (class_exists(Coroutine::class)) {
            return Coroutine::id();
        }
        return -1;
    }

    private function copyContext(int $fromCoroutineId): void
    {
        if (class_exists(Context::class) && ApplicationContext::hasContainer()) {
            $keys = config('odin.content_copy_keys', []);
            if (! empty($keys)) {
                Context::copy($fromCoroutineId, config('odin.content_copy_keys', []));
            }
        }
    }
}

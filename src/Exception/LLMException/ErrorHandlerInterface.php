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

namespace Hyperf\Odin\Exception\LLMException;

use Hyperf\Odin\Exception\LLMException;
use Throwable;

/**
 * LLM错误处理器接口.
 */
interface ErrorHandlerInterface
{
    /**
     * 处理异常.
     *
     * @param Throwable $exception 原始异常
     * @param array $context 上下文信息
     * @return LLMException 处理后的LLM异常
     */
    public function handle(Throwable $exception, array $context = []): LLMException;

    /**
     * 生成错误报告.
     *
     * @param LLMException $exception LLM异常
     * @param array $context 上下文信息
     * @return array 错误报告数据
     */
    public function generateErrorReport(LLMException $exception, array $context = []): array;

    /**
     * 记录错误信息.
     *
     * @param LLMException $exception LLM异常
     * @param array $context 上下文信息
     */
    public function logError(LLMException $exception, array $context = []): void;

    /**
     * 添加自定义错误映射规则.
     *
     * @param array $rules 自定义规则
     */
    public function addMappingRules(array $rules): void;
}

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

use GuzzleHttp\Exception\RequestException;
use Hyperf\Odin\Exception\LLMException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 错误映射管理器，负责将不同模型的错误映射为标准的LLM异常.
 */
class ErrorMappingManager
{
    /**
     * 错误映射配置.
     */
    protected array $mappings = [];

    /**
     * 日志记录器.
     */
    protected ?LoggerInterface $logger;

    /**
     * 自定义错误映射规则.
     */
    protected array $customMappingRules = [];

    /**
     * 构造函数，可接收自定义映射规则.
     */
    public function __construct(?LoggerInterface $logger = null, array $customMappingRules = [])
    {
        $this->logger = $logger;
        $this->customMappingRules = $customMappingRules;
        $this->initMappings();
    }

    /**
     * 将异常映射为LLM异常.
     *
     * @param Throwable $exception 原始异常
     * @param array $context 上下文信息
     */
    public function mapException(Throwable $exception, array $context = []): LLMException
    {
        // 如果已经是LLM异常，直接返回
        if ($exception instanceof LLMException) {
            return $exception;
        }

        // 记录原始异常
        $this->logger?->debug('开始映射异常', [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'context' => $context,
        ]);

        // 遍历映射规则，查找匹配的处理器
        foreach ($this->mappings as $exceptionClass => $handlers) {
            // 跳过默认处理器
            if ($exceptionClass === 'default') {
                continue;
            }

            // 检查异常类型是否匹配
            if ($exception instanceof $exceptionClass) {
                // 如果是单个处理器（非数组形式）
                if (isset($handlers['factory'])) {
                    if ($this->matchesPattern($exception, $handlers)) {
                        return $this->createException($handlers['factory'], $exception, $context);
                    }
                } else {
                    // 多个处理器，按顺序检查
                    foreach ($handlers as $handler) {
                        // 默认处理器
                        if (isset($handler['default']) && $handler['default']) {
                            $defaultHandler = $handler;
                            continue;
                        }

                        // 检查是否匹配模式
                        if ($this->matchesPattern($exception, $handler)) {
                            return $this->createException($handler['factory'], $exception, $context);
                        }
                    }

                    // 如果有默认处理器且没有找到匹配的，使用默认处理器
                    if (isset($defaultHandler)) {
                        return $this->createException($defaultHandler['factory'], $exception, $context);
                    }
                }
            }
        }

        // 如果没有找到匹配的映射，使用全局默认处理器
        if (isset($this->mappings['default']['factory'])) {
            return $this->createException($this->mappings['default']['factory'], $exception, $context);
        }

        // 最后的兜底：创建一个通用LLM异常
        return new LLMException('未处理的LLM错误: ' . $exception->getMessage(), 0, $exception);
    }

    /**
     * 添加自定义映射规则.
     */
    public function addMappingRules(array $rules): void
    {
        $this->customMappingRules = array_merge($this->customMappingRules, $rules);
        $this->initMappings(); // 重新初始化映射
    }

    /**
     * 初始化映射配置.
     */
    protected function initMappings(): void
    {
        // 获取默认映射
        $this->mappings = ErrorMapping::getDefaultMapping();

        // 合并自定义映射规则
        if (! empty($this->customMappingRules)) {
            $this->mergeMappingRules($this->customMappingRules);
        }
    }

    /**
     * 合并映射规则.
     */
    protected function mergeMappingRules(array $rules): void
    {
        foreach ($rules as $exceptionClass => $handlers) {
            // 如果映射中已经存在这个异常类
            if (isset($this->mappings[$exceptionClass])) {
                // 如果现有配置是单个处理器（有factory键）
                if (isset($this->mappings[$exceptionClass]['factory'])) {
                    // 转换为数组形式
                    $this->mappings[$exceptionClass] = [$this->mappings[$exceptionClass]];
                }

                // 将新规则添加到处理器列表开头（优先级更高）
                if (isset($handlers['factory'])) {
                    array_unshift($this->mappings[$exceptionClass], $handlers);
                } else {
                    // 多个处理器，依次添加到开头
                    foreach (array_reverse($handlers) as $handler) {
                        array_unshift($this->mappings[$exceptionClass], $handler);
                    }
                }
            } else {
                // 直接添加新的异常类映射
                $this->mappings[$exceptionClass] = $handlers;
            }
        }
    }

    /**
     * 检查异常是否匹配给定的模式.
     */
    protected function matchesPattern(Throwable $exception, array $handler): bool
    {
        // 检查正则表达式匹配
        if (isset($handler['regex'])) {
            $message = $exception->getMessage();
            if (! preg_match($handler['regex'], $message)) {
                return false;
            }
        }

        // 检查HTTP状态码匹配（针对RequestException）
        if (isset($handler['status']) && $exception instanceof RequestException && $exception->getResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            if (! in_array($statusCode, (array) $handler['status'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 使用工厂函数创建异常实例.
     */
    protected function createException(callable $factory, Throwable $exception, array $context): LLMException
    {
        try {
            $result = $factory($exception, $context);

            // 确保返回的是LLMException类型
            if ($result instanceof LLMException) {
                $this->logger?->debug('异常映射成功', [
                    'original_exception' => get_class($exception),
                    'mapped_exception' => get_class($result),
                    'error_code' => $result->getErrorCode(),
                    'context' => $context,
                ]);
                return $result;
            }

            $this->logger?->warning('异常映射工厂函数返回了非LLMException类型', [
                'original_exception' => get_class($exception),
                'returned_type' => get_class($result),
                'context' => $context,
            ]);
        } catch (Throwable $e) {
            $this->logger?->error('异常映射过程中发生错误', [
                'original_exception' => get_class($exception),
                'mapping_error' => $e->getMessage(),
                'context' => $context,
            ]);
        }

        // 如果出错，返回通用异常
        return new LLMException('LLM调用错误: ' . $exception->getMessage(), 0, $exception);
    }
}

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
use Psr\Log\LogLevel;
use Throwable;

/**
 * LLM错误处理器.
 */
class LLMErrorHandler implements ErrorHandlerInterface
{
    /**
     * 异常映射管理器.
     */
    protected ErrorMappingManager $errorMappingManager;

    /**
     * 日志记录器.
     */
    protected ?LoggerInterface $logger;

    /**
     * 是否在处理中记录错误.
     */
    protected bool $logErrors = true;

    /**
     * 是否生成详细的错误上下文.
     */
    protected bool $verboseErrorContext = true;

    /**
     * 创建错误处理器实例.
     */
    public function __construct(?LoggerInterface $logger = null, array $customMappingRules = [], bool $logErrors = true)
    {
        $this->logger = $logger;
        $this->logErrors = $logErrors;
        $this->errorMappingManager = new ErrorMappingManager($logger, $customMappingRules);
    }

    /**
     * 处理异常.
     *
     * @param Throwable $exception 原始异常
     * @param array $context 上下文信息
     * @return LLMException 处理后的LLM异常
     */
    public function handle(Throwable $exception, array $context = []): LLMException
    {
        try {
            // 主动提取Guzzle RequestException的响应头信息
            $enrichedContext = $this->enrichContextWithResponseInfo($exception, $context);

            // 将异常映射为标准的LLM异常
            $llmException = $this->errorMappingManager->mapException($exception, $enrichedContext);

            // 记录错误信息
            if ($this->logErrors) {
                $this->logError($llmException, $enrichedContext);
            }

            return $llmException;
        } catch (Throwable $e) {
            // 处理过程中发生错误，确保至少返回一个LLM异常
            $this->logger?->error('错误处理过程中发生异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'original_exception' => get_class($exception),
            ]);

            return new LLMException('处理LLM错误时发生异常: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * 生成错误报告.
     *
     * @param LLMException $exception LLM异常
     * @param array $context 上下文信息
     * @return array 错误报告数据
     */
    public function generateErrorReport(LLMException $exception, array $context = []): array
    {
        $report = [
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'error_code' => $exception->getErrorCode(),
                'type' => get_class($exception),
            ],
        ];

        // 添加HTTP状态码（如果有的话）
        if ($exception->getStatusCode()) {
            $report['error']['http_status_code'] = $exception->getStatusCode();
        }

        // 添加错误描述和建议（如果有）
        if (method_exists($exception, 'getDescription')) {
            try {
                $description = call_user_func([$exception, 'getDescription']);
                if ($description) {
                    $report['error']['description'] = $description;
                }
            } catch (Throwable $e) {
                // 忽略方法调用错误
            }
        }

        if (method_exists($exception, 'getSuggestion')) {
            try {
                $suggestion = call_user_func([$exception, 'getSuggestion']);
                if ($suggestion) {
                    $report['error']['suggestion'] = $suggestion;
                }
            } catch (Throwable $e) {
                // 忽略方法调用错误
            }
        } else {
            // 尝试从错误码获取建议
            $suggestion = ErrorCode::getSuggestion($exception->getErrorCode());
            if ($suggestion) {
                $report['error']['suggestion'] = $suggestion;
            }
        }

        // 添加额外的上下文信息
        if ($this->verboseErrorContext && ! empty($context)) {
            // 过滤敏感信息
            $safeContext = $this->filterSensitiveInfo($context);
            $report['context'] = $safeContext;
        }

        return $report;
    }

    /**
     * 记录错误信息.
     *
     * @param LLMException $exception LLM异常
     * @param array $context 上下文信息
     */
    public function logError(LLMException $exception, array $context = []): void
    {
        if (! $this->logger) {
            return;
        }

        // 根据错误代码确定日志级别
        $logLevel = $this->determineLogLevel($exception);

        // 生成错误ID，用于关联日志
        $errorId = uniqid('llm_err_');

        // 构建日志上下文
        $logContext = [
            'error_id' => $errorId,
            'error_type' => get_class($exception),
            'error_code' => $exception->getErrorCode(),
        ];

        // 添加HTTP状态码信息（如果有的话）
        if ($exception->getStatusCode()) {
            $logContext['http_status_code'] = $exception->getStatusCode();
        }

        // 添加异常追踪信息
        if ($exception->getPrevious()) {
            $logContext['original_error'] = $exception->getPrevious()->getMessage();
            $logContext['original_error_type'] = get_class($exception->getPrevious());
        }

        // 添加请求上下文
        if (! empty($context)) {
            // 过滤敏感信息
            $safeContext = $this->filterSensitiveInfo($context);
            $logContext['request_context'] = $safeContext;
        }

        // 记录日志
        $this->logger->log(
            $logLevel,
            sprintf('[%s] LLM错误: %s', $errorId, $exception->getMessage()),
            $logContext
        );
    }

    /**
     * 添加自定义错误映射规则.
     *
     * @param array $rules 自定义规则
     */
    public function addMappingRules(array $rules): void
    {
        $this->errorMappingManager->addMappingRules($rules);
    }

    /**
     * 设置是否在处理中记录错误.
     */
    public function setLogErrors(bool $logErrors): self
    {
        $this->logErrors = $logErrors;
        return $this;
    }

    /**
     * 设置是否生成详细的错误上下文.
     */
    public function setVerboseErrorContext(bool $verbose): self
    {
        $this->verboseErrorContext = $verbose;
        return $this;
    }

    /**
     * 根据异常确定日志级别.
     */
    protected function determineLogLevel(LLMException $exception): string
    {
        $errorCode = $exception->getErrorCode();

        // 配置错误（1000系列）- 警告级别
        if ($errorCode >= 1000 && $errorCode < 2000) {
            return LogLevel::WARNING;
        }

        // 网络错误（2000系列）- 错误级别
        if ($errorCode >= 2000 && $errorCode < 3000) {
            return LogLevel::ERROR;
        }

        // API错误（3000系列）
        if ($errorCode >= 3000 && $errorCode < 4000) {
            // 速率限制和无效请求 - 警告级别
            if ($errorCode == ErrorCode::API_RATE_LIMIT || $errorCode == ErrorCode::API_INVALID_REQUEST) {
                return LogLevel::WARNING;
            }
            // 其他API错误 - 错误级别
            return LogLevel::ERROR;
        }

        // 模型错误（4000系列）
        if ($errorCode >= 4000 && $errorCode < 5000) {
            // 内容过滤和上下文长度 - 警告级别
            if ($errorCode == ErrorCode::MODEL_CONTENT_FILTER || $errorCode == ErrorCode::MODEL_CONTEXT_LENGTH) {
                return LogLevel::WARNING;
            }
            // 其他模型错误 - 错误级别
            return LogLevel::ERROR;
        }

        // 默认为错误级别
        return LogLevel::ERROR;
    }

    /**
     * 过滤上下文中的敏感信息.
     */
    protected function filterSensitiveInfo(array $context): array
    {
        $filtered = [];
        $sensitiveKeys = ['api_key', 'api-key', 'apiKey', 'password', 'secret', 'token', 'authorization'];

        foreach ($context as $key => $value) {
            // 对于数字索引，直接处理值
            if (! is_string($key)) {
                if (is_array($value)) {
                    $filtered[$key] = $this->filterSensitiveInfo($value);
                } else {
                    $filtered[$key] = $value;
                }
                continue;
            }

            // 检查是否为敏感信息（只针对字符串键）
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                // 替换敏感值
                $filtered[$key] = '******';
            } elseif (is_array($value)) {
                // 递归处理数组
                $filtered[$key] = $this->filterSensitiveInfo($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * 从异常中提取响应信息并丰富上下文.
     *
     * @param Throwable $exception 原始异常
     * @param array $context 原始上下文
     * @return array 丰富后的上下文
     */
    protected function enrichContextWithResponseInfo(Throwable $exception, array $context): array
    {
        $previous = $exception->getPrevious();
        // 如果是Guzzle的RequestException且有响应对象，提取响应信息
        if ($previous instanceof RequestException && $previous->getResponse()) {
            $response = $previous->getResponse();

            // 提取响应头
            $context['response_headers'] = $response->getHeaders();
            $context['response_status_code'] = $response->getStatusCode();
            $context['response_reason_phrase'] = $response->getReasonPhrase();

            // 提取响应体（如果有且不是流）
            try {
                $body = $response->getBody();
                if ($body->isSeekable()) {
                    $body->rewind();
                }
                $responseContent = $body->getContents();

                // 如果响应体不为空且较小（避免记录过大的响应体）
                if (! empty($responseContent) && strlen($responseContent) < 10240) {
                    $context['response_body'] = $responseContent;
                }

                // 重新设置流位置，以便后续处理
                if ($body->isSeekable()) {
                    $body->rewind();
                }
            } catch (Throwable $e) {
                // 如果无法读取响应体，记录但不影响主流程
                $this->logger?->debug('无法读取响应体内容', [
                    'error' => $e->getMessage(),
                    'status_code' => $response->getStatusCode(),
                ]);
            }

            // 记录HTTP错误响应信息到日志
            $this->logger?->info('HTTPErrorResponseInfo', [
                'status_code' => $response->getStatusCode(),
                'reason_phrase' => $response->getReasonPhrase(),
                'headers' => $response->getHeaders(),
                'has_body' => isset($context['response_body']),
                'content' => $context['response_body'] ?? null,
            ]);
        }

        return $context;
    }
}

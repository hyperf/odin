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

namespace Hyperf\Odin\Api\Providers;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Request\CompletionRequest;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Api\Response\TextCompletionResponse;
use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Contract\Api\ConfigInterface;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Event\AfterEmbeddingsEvent;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Utils\EventUtil;
use Hyperf\Odin\Utils\LoggingConfigHelper;
use Hyperf\Odin\Utils\LogUtil;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * API客户端抽象基类.
 */
abstract class AbstractClient implements ClientInterface
{
    protected GuzzleClient $client;

    protected ConfigInterface $config;

    protected ApiOptions $requestOptions;

    protected ?LoggerInterface $logger;

    /**
     * 错误映射管理器.
     */
    protected ErrorMappingManager $errorMappingManager;

    /**
     * 错误处理器.
     */
    protected ?ErrorHandlerInterface $errorHandler = null;

    public function __construct(ConfigInterface $config, ApiOptions $requestOptions, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->requestOptions = $requestOptions;
        $this->initClient();
        $this->initErrorMappingManager();
    }

    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $options = $chatRequest->createOptions();
        $requestId = $this->addRequestIdToOptions($options);
        $url = $this->buildChatCompletionsUrl();

        $this->logRequest('ChatCompletionsRequest', $url, $options, $requestId);

        $startTime = microtime(true);
        try {
            $response = $this->client->post($url, $options);
            $duration = $this->calculateDuration($startTime);
            $chatCompletionResponse = new ChatCompletionResponse($response, $this->logger);

            $this->logResponse('ChatCompletionsResponse', $requestId, $duration, [
                'content' => $chatCompletionResponse->getContent(),
                'response_headers' => $response->getHeaders(),
            ]);

            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatCompletionResponse, $duration));

            return $chatCompletionResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url, $options, 'completions'));
        }
    }

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->setStream(true);
        $chatRequest->validate();
        $options = $chatRequest->createOptions();
        $requestId = $this->addRequestIdToOptions($options);
        $url = $this->buildChatCompletionsUrl();

        $this->logRequest('ChatCompletionsStreamRequest', $url, $options, $requestId);

        $startTime = microtime(true);
        try {
            $options[RequestOptions::STREAM] = true;
            $response = $this->client->post($url, $options);
            $firstResponseDuration = $this->calculateDuration($startTime);

            $stream = $response->getBody()->detach();
            $sseClient = new SSEClient(
                $stream,
                true,
                (int) $this->requestOptions->getTotalTimeout(),
                $this->requestOptions->getTimeout(),
                $this->logger
            );

            $chatCompletionStreamResponse = new ChatCompletionStreamResponse($response, $this->logger, $sseClient);
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent(new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration));

            $this->logResponse('ChatCompletionsStreamResponse', $requestId, $firstResponseDuration, [
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $response->getHeaders(),
            ]);

            return $chatCompletionStreamResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url, $options, 'stream'));
        }
    }

    public function embeddings(EmbeddingRequest $embeddingRequest): EmbeddingResponse
    {
        $embeddingRequest->validate();
        $options = $embeddingRequest->createOptions();
        $requestId = $this->addRequestIdToOptions($options);
        $url = $this->buildEmbeddingsUrl();

        $this->logRequest('EmbeddingsRequest', $url, $options, $requestId);

        $startTime = microtime(true);
        try {
            $response = $this->client->post($url, $options);
            $duration = $this->calculateDuration($startTime);
            $embeddingResponse = new EmbeddingResponse($response, $this->logger);

            $this->logResponse('EmbeddingsResponse', $requestId, $duration, [
                'data' => $embeddingResponse->toArray(),
                'response_headers' => $response->getHeaders(),
            ]);

            EventUtil::dispatch(new AfterEmbeddingsEvent($embeddingRequest, $embeddingResponse, $duration));

            return $embeddingResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url, $options, 'embeddings'));
        }
    }

    public function completions(CompletionRequest $completionRequest): TextCompletionResponse
    {
        $completionRequest->validate();
        $options = $completionRequest->createOptions();
        $requestId = $this->addRequestIdToOptions($options);
        $url = $this->buildCompletionsUrl();

        $this->logRequest('CompletionsRequest', $url, $options, $requestId);

        $startTime = microtime(true);
        try {
            $response = $this->client->post($url, $options);
            $duration = $this->calculateDuration($startTime);
            $completionResponse = new TextCompletionResponse($response, $this->logger);

            $this->logResponse('CompletionsResponse', $requestId, $duration, [
                'choices' => $completionResponse->getContent(),
            ]);

            return $completionResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url, $options, 'completions'));
        }
    }

    public function getRequestOptions(): ApiOptions
    {
        return $this->requestOptions;
    }

    /**
     * 构建聊天补全API的URL.
     */
    abstract protected function buildChatCompletionsUrl(): string;

    /**
     * 构建嵌入接口URL.
     */
    abstract protected function buildEmbeddingsUrl(): string;

    /**
     * 构建文本补全API的URL.
     */
    abstract protected function buildCompletionsUrl(): string;

    /**
     * 获取认证头信息.
     */
    protected function getAuthHeaders(): array
    {
        return [];
    }

    /**
     * 获取基础URI.
     */
    protected function getBaseUri(): string
    {
        return rtrim($this->config->getBaseUrl(), '/');
    }

    /**
     * 初始化错误映射管理器.
     */
    protected function initErrorMappingManager(): void
    {
        $this->errorMappingManager = new ErrorMappingManager(
            $this->logger,
            $this->requestOptions->getCustomErrorMappingRules()
        );
    }

    /**
     * 获取错误处理器.
     */
    protected function getErrorHandler(): ErrorHandlerInterface
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = new LLMErrorHandler($this->logger);
        }
        return $this->errorHandler;
    }

    /**
     * 转换异常为标准的LLM异常，并进行错误处理.
     */
    protected function convertException(Throwable $exception, array $context = []): LLMException
    {
        // 先通过映射管理器转换异常
        $mappedException = $this->errorMappingManager->mapException($exception, $context);

        // 然后通过错误处理器进行进一步处理
        return $this->getErrorHandler()->handle($mappedException, $context);
    }

    /**
     * 初始化HTTP客户端.
     */
    protected function initClient(): void
    {
        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
            // 设置更细粒度的超时配置
            RequestOptions::TIMEOUT => $this->requestOptions->getTotalTimeout(),
            RequestOptions::CONNECT_TIMEOUT => $this->requestOptions->getConnectionTimeout(),
        ];
        if ($this->getBaseUri()) {
            $options['base_uri'] = $this->getBaseUri();
        }
        if ($this->requestOptions->getProxy()) {
            $options['proxy'] = $this->requestOptions->getProxy();
        }

        // 从 requestOptions 获取 HTTP 处理器配置
        $handlerType = $this->requestOptions->getHttpHandler();

        // 使用配置的 HTTP 处理器创建客户端
        $this->client = HttpHandlerFactory::createGuzzleClient($options, $handlerType);
    }

    /**
     * 生成唯一的请求ID.
     */
    protected function generateRequestId(): string
    {
        return 'req_' . date('YmdHis') . '_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * 为请求选项添加请求ID.
     */
    protected function addRequestIdToOptions(array &$options): string
    {
        $requestId = $this->generateRequestId();
        if (! isset($options[RequestOptions::HEADERS])) {
            $options[RequestOptions::HEADERS] = [];
        }
        $options[RequestOptions::HEADERS]['x-request-id'] = $requestId;
        return $requestId;
    }

    /**
     * 记录请求日志.
     */
    protected function logRequest(string $logType, string $url, array $options, string $requestId): void
    {
        $logData = [
            'url' => $url,
            'options' => $options,
            'request_id' => $requestId,
        ];

        $this->logger?->info($logType, LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));
    }

    /**
     * 记录响应日志.
     */
    protected function logResponse(string $logType, string $requestId, float $duration, array $additionalData = []): void
    {
        $performanceFlag = LogUtil::getPerformanceFlag($duration);

        $logData = array_merge([
            'request_id' => $requestId,
            'duration_ms' => $duration,
            'performance_flag' => $performanceFlag,
        ], $additionalData);

        $this->logger?->info($logType, LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));
    }

    /**
     * 创建异常处理上下文.
     */
    protected function createExceptionContext(string $url, array $options, string $mode): array
    {
        return [
            'url' => $url,
            'options' => $options,
            'mode' => $mode,
            'api_options' => $this->requestOptions->toArray(),
        ];
    }

    /**
     * 计算请求持续时间（毫秒）.
     */
    protected function calculateDuration(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000);
    }

    /**
     * 获取请求头.
     */
    private function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Hyperf-Odin/1.0',
        ];

        // 合并认证头
        return array_merge($headers, $this->getAuthHeaders());
    }
}

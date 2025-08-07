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

        $url = $this->buildChatCompletionsUrl();

        $this->logger?->info('ChatCompletionsRequest', LoggingConfigHelper::filterAndFormatLogData(['url' => $url, 'options' => $options], $this->requestOptions));

        $startTime = microtime(true);
        try {
            $response = $this->client->post($url, $options);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            $chatCompletionResponse = new ChatCompletionResponse($response, $this->logger);

            $performanceFlag = LogUtil::getPerformanceFlag($duration);
            $logData = [
                'duration_ms' => $duration,
                'content' => $chatCompletionResponse->getContent(),
                'response_headers' => $response->getHeaders(),
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('ChatCompletionsResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatCompletionResponse, $duration));

            return $chatCompletionResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, [
                'url' => $url,
                'options' => $options,
                'mode' => 'completions',
                'api_options' => $this->requestOptions->toArray(),
            ]);
        }
    }

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->setStream(true);
        $chatRequest->validate();
        $options = $chatRequest->createOptions();

        $url = $this->buildChatCompletionsUrl();

        $this->logger?->info('ChatCompletionsStreamRequest', LoggingConfigHelper::filterAndFormatLogData(['url' => $url, 'options' => $options], $this->requestOptions));

        $startTime = microtime(true);
        try {
            $options[RequestOptions::STREAM] = true;
            $response = $this->client->post($url, $options);

            $firstResponseTime = microtime(true);
            $firstResponseDuration = round(($firstResponseTime - $startTime) * 1000); // 毫秒

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

            $performanceFlag = LogUtil::getPerformanceFlag($firstResponseDuration);
            $logData = [
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $response->getHeaders(),
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('ChatCompletionsStreamResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            return $chatCompletionStreamResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, [
                'url' => $url,
                'options' => $options,
                'mode' => 'stream',
                'api_options' => $this->requestOptions->toArray(),
            ]);
        }
    }

    public function embeddings(EmbeddingRequest $embeddingRequest): EmbeddingResponse
    {
        $embeddingRequest->validate();
        $options = $embeddingRequest->createOptions();

        $url = $this->buildEmbeddingsUrl();

        $this->logger?->info('EmbeddingsRequestRequest', ['url' => $url, 'options' => $options]);

        $startTime = microtime(true);

        try {
            $response = $this->client->post($url, $options);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            $embeddingResponse = new EmbeddingResponse($response, $this->logger);

            $this->logger?->info('EmbeddingsResponse', [
                'duration_ms' => $duration,
                'data' => $embeddingResponse->toArray(),
            ]);

            EventUtil::dispatch(new AfterEmbeddingsEvent($embeddingRequest, $embeddingResponse, $duration));

            return $embeddingResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, [
                'url' => $url,
                'options' => $options,
                'mode' => 'embeddings',
                'api_options' => $this->requestOptions->toArray(),
            ]);
        }
    }

    public function completions(CompletionRequest $completionRequest): TextCompletionResponse
    {
        $completionRequest->validate();
        $options = $completionRequest->createOptions();
        $url = $this->buildCompletionsUrl();

        $this->logger?->info('CompletionsRequest', ['url' => $url, 'options' => $options]);

        $startTime = microtime(true);
        try {
            $response = $this->client->post($url, $options);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            $completionResponse = new TextCompletionResponse($response, $this->logger);

            $this->logger?->info('CompletionsResponse', [
                'duration_ms' => $duration,
                'choices' => $completionResponse->getContent(),
            ]);

            return $completionResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, [
                'url' => $url,
                'options' => $options,
                'mode' => 'completions',
                'api_options' => $this->requestOptions->toArray(),
            ]);
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
        $this->logger->info('RequestOptions', $this->requestOptions->toArray());
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

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

namespace Hyperf\Odin\Api\Providers\DashScope;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeCachePointManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Utils\EventUtil;
use Psr\Log\LoggerInterface;
use Throwable;

class Client extends AbstractClient
{
    private ?DashScopeCachePointManager $cachePointManager = null;

    public function __construct(
        DashScopeConfig $config,
        ?ApiOptions $requestOptions = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($config, $requestOptions, $logger);

        // 总是初始化缓存点管理器
        $this->cachePointManager = new DashScopeCachePointManager($config->getAutoCacheConfig());
    }

    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            // 应用缓存点配置（自动或手动验证）
            $this->cachePointManager->configureCachePoints($chatRequest);

            $options = $chatRequest->createOptions();

            // 处理缓存点转换并决定是否添加缓存控制头部
            $hasCachePoints = $this->processCachePoints($chatRequest, $options);

            $url = $this->buildChatCompletionsUrl();
            $requestId = $this->addRequestIdToOptions($options);

            // 根据是否有缓存点添加缓存控制头部
            if ($hasCachePoints) {
                $this->addCacheControlHeader($options);
            }

            $this->logRequest('DashScopeChatRequest', $url, $options, $requestId);

            $response = $this->client->post($url, $options);
            $duration = $this->calculateDuration($startTime);

            $chatResponse = new ChatCompletionResponse($response, $this->logger);

            $this->logResponse('DashScopeChatResponse', $requestId, $duration, [
                'content' => $chatResponse->getContent(),
                'usage' => $chatResponse->getUsage(),
                'response_headers' => $response->getHeaders(),
            ]);

            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatResponse, $duration));

            return $chatResponse;
        } catch (Throwable $e) {
            $context = $this->createExceptionContext($url ?? '', $options ?? [], 'completions');

            throw $this->convertException($e, $context);
        }
    }

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->validate();
        $chatRequest->setStream(true);

        $this->cachePointManager->configureCachePoints($chatRequest);

        $options = $chatRequest->createOptions();
        $hasCachePoints = $this->processCachePoints($chatRequest, $options);

        $url = $this->buildChatCompletionsUrl();
        $requestId = $this->addRequestIdToOptions($options);

        // 根据是否有缓存点添加缓存控制头部
        if ($hasCachePoints) {
            $this->addCacheControlHeader($options);
        }

        $this->logRequest('DashScopeChatStreamRequest', $url, $options, $requestId);

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
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent(
                new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration)
            );

            $this->logResponse('DashScopeChatStreamResponse', $requestId, $firstResponseDuration, [
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $response->getHeaders(),
            ]);

            return $chatCompletionStreamResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url, $options, 'stream'));
        }
    }

    protected function getAuthHeaders(): array
    {
        $headers = [];
        /** @var DashScopeConfig $config */
        $config = $this->config;

        if ($config->getApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $config->getApiKey();
        }

        return $headers;
    }

    /**
     * 构建聊天补全API的URL.
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/chat/completions';
    }

    /**
     * 构建嵌入API的URL.
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->getBaseUri() . '/embeddings';
    }

    /**
     * 构建文本补全API的URL.
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/completions';
    }

    /**
     * 将 Odin 的 CachePoint 转换为 DashScope 的 cache_control 格式.
     *
     * @return bool 是否有缓存点被处理
     */
    private function processCachePoints(ChatCompletionRequest $request, array &$options): bool
    {
        if (! isset($options['json']['messages'])) {
            return false;
        }

        $messages = $request->getMessages();
        $jsonMessages = &$options['json']['messages'];
        $hasCachePoints = false;

        foreach ($messages as $index => $message) {
            $cachePoint = $message->getCachePoint();

            if ($cachePoint && $cachePoint->getType() === 'ephemeral') {
                $this->addCacheControlToMessage($jsonMessages[$index]);
                $hasCachePoints = true;
            }
        }

        return $hasCachePoints;
    }

    /**
     * 为消息添加 cache_control 标记.
     */
    private function addCacheControlToMessage(array &$message): void
    {
        if (is_string($message['content'])) {
            $message['content'] = [
                [
                    'type' => 'text',
                    'text' => $message['content'],
                ],
            ];
        }

        if (is_array($message['content']) && ! empty($message['content'])) {
            $lastIndex = count($message['content']) - 1;
            $message['content'][$lastIndex]['cache_control'] = [
                'type' => 'ephemeral',
            ];
        }
    }

    /**
     * 添加缓存控制头部.
     */
    private function addCacheControlHeader(array &$options): void
    {
        if (! isset($options['headers'])) {
            $options['headers'] = [];
        }

        $options['headers']['X-DashScope-CacheControl'] = 'enable';
    }
}

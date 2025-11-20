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

namespace Hyperf\Odin\Api\Providers\Gemini;

use GuzzleHttp\RequestOptions;
use Hyperf\Engine\Coroutine;
use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Transport\OdinSimpleCurl;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Utils\EventUtil;
use Psr\Log\LoggerInterface;
use Throwable;

class Client extends AbstractClient
{
    public function __construct(GeminiConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }
        parent::__construct($config, $requestOptions, $logger);
    }

    /**
     * Chat completions using Gemini native API.
     */
    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            $model = $chatRequest->getModel();

            // Convert request to Gemini native format
            $geminiRequest = RequestHandler::convertRequest($chatRequest, $model);

            // Check and apply cache if available
            $geminiRequest = $this->checkAndApplyCache($geminiRequest, $chatRequest);

            // Build URL for Gemini native API
            $url = $this->buildGeminiUrl($model, false);

            // Prepare request options
            $options = [
                RequestOptions::JSON => $geminiRequest,
                RequestOptions::HEADERS => $this->getHeaders(),
            ];

            $requestId = $this->addRequestIdToOptions($options);

            $this->logRequest('GeminiChatRequest', $url, $options, $requestId);

            // Send request
            $response = $this->client->post($url, $options);
            $duration = $this->calculateDuration($startTime);

            // Parse Gemini response
            $geminiResponse = json_decode($response->getBody()->getContents(), true);

            // Convert to OpenAI format
            $standardResponse = ResponseHandler::convertResponse($geminiResponse, $model);
            $chatResponse = new ChatCompletionResponse($standardResponse, $this->logger);

            $this->logResponse('GeminiChatResponse', $requestId, $duration, [
                'content' => $chatResponse->getFirstChoice()?->getMessage()?->toArray(),
                'usage' => $chatResponse->getUsage()?->toArray(),
                'response_headers' => $response->getHeaders(),
            ]);

            // Create event and register cache callback
            $event = new AfterChatCompletionsEvent($chatRequest, $chatResponse, $duration);
            $this->registerCacheCallback($event, $chatRequest);
            // Event listener will execute callbacks
            EventUtil::dispatch($event);

            return $chatResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url ?? '', $options ?? [], 'completions'));
        }
    }

    /**
     * Chat completions streaming using Gemini native API.
     */
    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->validate();
        $chatRequest->setStream(true);
        $startTime = microtime(true);

        try {
            $model = $chatRequest->getModel();

            // Convert request to Gemini native format
            $geminiRequest = RequestHandler::convertRequest($chatRequest, $model);

            // Check and apply cache if available
            $geminiRequest = $this->checkAndApplyCache($geminiRequest, $chatRequest);

            // Build URL for Gemini streaming API
            $url = $this->buildGeminiUrl($model, true);

            // Prepare request options
            $options = [
                RequestOptions::JSON => $geminiRequest,
                RequestOptions::STREAM => true,
                RequestOptions::TIMEOUT => $this->requestOptions->getStreamFirstChunkTimeout(),
            ];

            $requestId = $this->addRequestIdToOptions($options);

            $this->logRequest('GeminiChatStreamRequest', $url, $options, $requestId);

            // Send streaming request
            if (Coroutine::id()) {
                foreach ($this->getHeaders() as $key => $value) {
                    $options['headers'][$key] = $value;
                }
                $options['connect_timeout'] = $this->requestOptions->getConnectionTimeout();
                $options['stream_chunk'] = $this->requestOptions->getStreamChunkTimeout();
                $options['header_timeout'] = $this->requestOptions->getStreamFirstChunkTimeout();
                if ($proxy = $this->requestOptions->getProxy()) {
                    $options['proxy'] = $proxy;
                }
                $response = OdinSimpleCurl::send($url, $options);
            } else {
                $response = $this->client->post($url, $options);
            }

            $firstResponseDuration = $this->calculateDuration($startTime);

            // Create stream converter
            $streamConverter = new StreamConverter($response, $this->logger, $model);

            $chatCompletionStreamResponse = new ChatCompletionStreamResponse(
                logger: $this->logger,
                streamIterator: $streamConverter
            );
            // Create event and register cache callback
            $streamEvent = new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration);
            $this->registerCacheCallback($streamEvent, $chatRequest);
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent($streamEvent);

            $this->logResponse('GeminiChatStreamResponse', $requestId, $firstResponseDuration, [
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $response->getHeaders(),
            ]);

            return $chatCompletionStreamResponse;
        } catch (Throwable $e) {
            throw $this->convertException($e, $this->createExceptionContext($url ?? '', $options ?? [], 'stream'));
        }
    }

    /**
     * Build chat completions API URL (for compatibility).
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/chat/completions';
    }

    /**
     * Build embeddings API URL.
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->getBaseUri() . '/embeddings';
    }

    /**
     * Build text completions API URL.
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/completions';
    }

    /**
     * Get authentication headers for Gemini API.
     */
    protected function getAuthHeaders(): array
    {
        $headers = [];
        /** @var GeminiConfig $config */
        $config = $this->config;

        // Gemini uses x-goog-api-key header instead of Authorization
        if ($config->getApiKey()) {
            $headers['x-goog-api-key'] = $config->getApiKey();
        }

        return $headers;
    }

    /**
     * Check and apply cache to geminiRequest if available.
     * If cache is available, apply it; otherwise return the original request.
     *
     * @param array $geminiRequest Gemini native format request
     * @param ChatCompletionRequest $chatRequest Original request
     * @return array Gemini native format request (with cache applied if available)
     */
    protected function checkAndApplyCache(array $geminiRequest, ChatCompletionRequest $chatRequest): array
    {
        /** @var GeminiConfig $config */
        $config = $this->config;

        // Check if auto cache is enabled
        if (! $config->isAutoCache()) {
            return $geminiRequest;
        }

        $cacheConfig = $config->getCacheConfig();
        if (! $cacheConfig) {
            return $geminiRequest;
        }

        try {
            /** @var GeminiConfig $geminiConfig */
            $geminiConfig = $this->config;
            $cacheManager = new GeminiCacheManager(
                $cacheConfig,
                $this->getRequestOptions(),
                $geminiConfig,
                $this->logger
            );
            $cacheInfo = $cacheManager->checkCache($chatRequest);
            var_dump($cacheInfo);
            if ($cacheInfo) {
                return $this->applyCacheToRequest($geminiRequest, $cacheInfo, $chatRequest);
            }
        } catch (Throwable $e) {
            // Log error but don't fail the request
            $this->logger?->warning('Failed to check Gemini cache', [
                'error' => $e->getMessage(),
            ]);
        }

        return $geminiRequest;
    }

    /**
     * Register cache callback to event.
     */
    protected function registerCacheCallback(AfterChatCompletionsEvent $event, ChatCompletionRequest $chatRequest): void
    {
        /** @var GeminiConfig $config */
        $config = $this->config;

        // Check if auto cache is enabled
        if (! $config->isAutoCache()) {
            return;
        }

        $cacheConfig = $config->getCacheConfig();
        if (! $cacheConfig) {
            return;
        }

        // Register callback to handle cache creation after request
        /** @var GeminiConfig $geminiConfig */
        $geminiConfig = $this->config;
        $apiOptions = $this->getRequestOptions();
        $logger = $this->logger;

        $event->addCallback(function (AfterChatCompletionsEvent $event) use ($cacheConfig, $chatRequest, $geminiConfig, $apiOptions, $logger) {
            try {
                // 1. 更新 request 的实际 tokens（从 response usage 中获取）
                $response = $event->getCompletionResponse();
                $usage = $response->getUsage();
                if ($usage) {
                    // 使用实际的 total tokens 更新估算值
                    // 在多轮对话中，补全的 tokens 会被应用到下一次对话中，所以应该使用 totalTokens
                    // totalTokens = promptTokens + completionTokens
                    $chatRequest->updateTokenEstimateFromUsage($usage->getTotalTokens());
                }

                // 2. 创建或更新缓存
                $cacheManager = new GeminiCacheManager(
                    $cacheConfig,
                    $apiOptions,
                    $geminiConfig,
                    $logger
                );
                $cacheManager->createOrUpdateCacheAfterRequest($chatRequest);
            } catch (Throwable $e) {
                // Log error but don't fail the request
                $logger?->warning('Failed to handle Gemini cache after request', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Apply cache to geminiRequest.
     * Remove cached content (system_instruction, tools, cached messages) and add cached_content.
     */
    protected function applyCacheToRequest(array $geminiRequest, array $cacheInfo, ChatCompletionRequest $chatRequest): array
    {
        // Add cached_content
        $geminiRequest['cached_content'] = $cacheInfo['cache_name'];

        // Remove system_instruction if cached
        if ($cacheInfo['has_system'] && isset($geminiRequest['system_instruction'])) {
            unset($geminiRequest['system_instruction']);
        }

        // Remove tools if cached
        if ($cacheInfo['has_tools'] && isset($geminiRequest['tools'])) {
            unset($geminiRequest['tools']);
        }

        // Remove cached messages from contents
        $cachedMessageCount = $cacheInfo['cached_message_count'] ?? 0;
        if ($cachedMessageCount > 0 && isset($geminiRequest['contents']) && is_array($geminiRequest['contents'])) {
            // Remove the first N messages from contents (these are already cached)
            $geminiRequest['contents'] = array_slice($geminiRequest['contents'], $cachedMessageCount);
        }

        return $geminiRequest;
    }

    /**
     * Build Gemini native API URL.
     */
    private function buildGeminiUrl(string $model, bool $stream): string
    {
        $baseUri = $this->getBaseUri();
        $endpoint = $stream ? 'streamGenerateContent' : 'generateContent';

        // URL format: https://generativelanguage.googleapis.com/v1beta/models/{model}:{endpoint}
        $url = "{$baseUri}/models/{$model}:{$endpoint}";

        // Add alt=sse parameter for streaming requests (SSE format)
        if ($stream) {
            $url .= '?alt=sse';
        }

        return $url;
    }
}

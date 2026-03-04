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
use Hyperf\Odin\Api\Providers\Gemini\Cache\CacheInfo;
use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Transport\OdinSimpleCurl;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Utils\EventUtil;
use Psr\Log\LoggerInterface;
use RuntimeException;
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

            // Prepare request with cache handling
            ['geminiRequest' => $geminiRequest, 'cacheWriteTokens' => $cacheWriteTokens] = $this->prepareRequestWithCache($chatRequest, $model);

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

            // Calculate context hash for thought signature caching
            // This is the cumulative hash of all messages sent in the request
            $messages = $chatRequest->getMessages();
            $contextHash = ThoughtSignatureCache::calculateContextHash($messages, count($messages));

            // Convert to OpenAI format with cache write tokens and context hash
            $standardResponse = ResponseHandler::convertResponse($geminiResponse, $model, $cacheWriteTokens, $contextHash);
            $chatResponse = new ChatCompletionResponse($standardResponse, $this->logger);

            // Cache thought signatures from tool calls
            $this->cacheThoughtSignatures($chatResponse);

            $this->logResponse('GeminiChatResponse', $requestId, $duration, [
                'content' => $chatResponse->getFirstChoice()?->getMessage()?->toArray(),
                'usage' => $chatResponse->getUsage()?->toArray(),
                'response_headers' => $response->getHeaders(),
                'original_response_usage' => $geminiResponse['usageMetadata'] ?? [],
            ]);

            // Dispatch event (cache has already been created synchronously if needed)
            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatResponse, $duration));

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

            // Prepare request with cache handling
            ['geminiRequest' => $geminiRequest, 'cacheWriteTokens' => $cacheWriteTokens] = $this->prepareRequestWithCache($chatRequest, $model);

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

            // Calculate context hash for thought signature caching
            // This is the cumulative hash of all messages sent in the request
            $messages = $chatRequest->getMessages();
            $contextHash = ThoughtSignatureCache::calculateContextHash($messages, count($messages));

            // Create stream converter with cache write tokens and context hash
            $streamConverter = new StreamConverter($response, $this->logger, $model, $cacheWriteTokens, $contextHash);

            $chatCompletionStreamResponse = new ChatCompletionStreamResponse(
                logger: $this->logger,
                streamIterator: $streamConverter
            );

            // Dispatch event (cache has already been created synchronously if needed)
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent(
                new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration)
            );

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
     * 规范化模型名称为 Gemini 标准格式.
     * 根据不同的 API 平台使用不同的格式：
     * - Generative Language API (generativelanguage.googleapis.com): "models/{model}"
     * - AI Platform API (aiplatform.googleapis.com):
     *   - 简短格式: "publishers/google/models/{model}"
     *   - 完整资源路径: "projects/{project}/locations/{location}/publishers/{publisher}/models/{model}".
     *
     * @param string $model 原始模型名称
     * @return string 标准格式的模型名称
     */
    public function normalizeModelName(string $model): string
    {
        // 如果是 AI Platform 完整资源路径格式 projects/{project}/locations/{location}/publishers/*/models/*
        // 直接返回，不做任何转换
        if (str_starts_with($model, 'projects/')) {
            return $model;
        }

        $isAiPlatform = $this->isAiPlatformApi();

        if ($isAiPlatform) {
            // AI Platform API 格式: publishers/google/models/{model}
            // 如果已经是完整格式，直接返回
            if (str_starts_with($model, 'publishers/google/models/')) {
                return $model;
            }

            // 如果是 models/ 开头，提取模型名称
            if (str_starts_with($model, 'models/')) {
                $modelName = substr($model, 7); // 去掉 "models/"
                return 'publishers/google/models/' . $modelName;
            }

            // 否则直接添加前缀
            return 'publishers/google/models/' . $model;
        }

        // Generative Language API 格式: models/{model}
        // 如果已经是标准格式 "models/xxx"，直接返回
        if (str_starts_with($model, 'models/')) {
            return $model;
        }

        // 如果是 publishers/google/models/ 开头，提取模型名称
        if (str_starts_with($model, 'publishers/google/models/')) {
            $modelName = substr($model, 25); // 去掉 "publishers/google/models/"
            return 'models/' . $modelName;
        }

        // 否则添加 "models/" 前缀
        return 'models/' . $model;
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

        // 判断是否使用 AI Platform API
        if ($this->isAiPlatformApi()) {
            // AI Platform API 使用 Service Account 认证
            $serviceAccountConfig = $config->getServiceAccountConfig();

            if (! $serviceAccountConfig) {
                throw new RuntimeException(
                    'Service Account configuration is required for AI Platform API. '
                    . 'Please provide ServiceAccountConfig in GeminiConfig.'
                );
            }

            // 创建凭证管理器获取认证头
            $credentialsManager = new ServiceAccountCredentialsManager(
                $serviceAccountConfig,
                $this->requestOptions,
                $this->logger
            );

            return $credentialsManager->getAuthHeaders();
        }

        // Generative Language API 使用 API Key 认证
        // Gemini uses x-goog-api-key header instead of Authorization
        if ($config->getApiKey()) {
            $headers['x-goog-api-key'] = $config->getApiKey();
        }

        return $headers;
    }

    /**
     * Check cache availability and create if needed.
     * Returns cache info without modifying the request.
     *
     * @param ChatCompletionRequest $chatRequest Original request
     * @return null|CacheInfo Cache information if cache is used/created, null otherwise
     */
    protected function checkCache(ChatCompletionRequest $chatRequest): ?CacheInfo
    {
        /** @var GeminiConfig $config */
        $config = $this->config;

        // Check if auto cache is enabled
        if (! $config->isAutoCache()) {
            return null;
        }

        $cacheConfig = $config->getCacheConfig();
        if (! $cacheConfig) {
            return null;
        }

        // 只有 publishers/google/models/* 格式的模型不支持缓存
        // projects/{project}/locations/{location}/publishers/*/models/* 格式支持 Vertex AI 缓存
        $model = $chatRequest->getModel();
        if (str_starts_with($model, 'publishers/google/models/')) {
            $cacheConfig->setEnableCache(false);
        }

        try {
            /** @var GeminiConfig $geminiConfig */
            $geminiConfig = $this->config;
            $cacheManager = new GeminiCacheManager(
                $cacheConfig,
                $this->getRequestOptions(),
                $geminiConfig,
                $this->logger,
            );
            $cacheInfo = $cacheManager->checkCache($chatRequest);
            if ($cacheInfo) {
                $this->logger?->info('Gemini cache available', [
                    'cache_name' => $cacheInfo->getCacheName(),
                    'is_newly_created' => $cacheInfo->isNewlyCreated(),
                    'cache_write_tokens' => $cacheInfo->getCacheWriteTokens(),
                    'cached_message_count' => count($cacheInfo->getCachedMessageHashes()),
                ]);
                return $cacheInfo;
            }
        } catch (Throwable $e) {
            // Log error but don't fail the request
            $this->logger?->warning('Failed to check or create Gemini cache', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Prepare ChatCompletionRequest for conversion by filtering cached messages.
     * Returns a new request with only uncached messages and without cached tools/system if needed.
     *
     * @param ChatCompletionRequest $chatRequest Original request
     * @param null|CacheInfo $cacheInfo Cache information
     */
    protected function prepareRequestForCache(ChatCompletionRequest $chatRequest, ?CacheInfo $cacheInfo): void
    {
        // If no cache, return original request
        if (! $cacheInfo) {
            return;
        }

        // Remove system message and filter cached messages
        $messages = $chatRequest->getMessages();

        // 过滤掉已经在缓存中的 hash 消息值，有缓存代表 system+tools 已经在缓存中了
        $newMessages = [];
        foreach ($messages as $message) {
            $hash = $message->getHash();
            if (! in_array($hash, $cacheInfo->getCachedMessageHashes(), true)) {
                $newMessages[] = $message;
            }
        }

        // Safety check: ensure at least one message remains
        // This prevents "contents is not specified" error when all messages are filtered
        if (empty($newMessages) && ! empty($messages)) {
            // Keep the last message (current user message should not be cached)
            $lastMessage = end($messages);
            $newMessages[] = $lastMessage;

            $this->logger?->warning('All messages were filtered by cache, keeping last message', [
                'last_message_hash' => $lastMessage->getHash(),
                'total_messages' => count($messages),
            ]);
        }

        $chatRequest->setFilterMessages($newMessages);
        $chatRequest->setMessages($newMessages);
        $chatRequest->setTools([]);
    }

    /**
     * Prepare Gemini request with cache handling.
     * This method consolidates cache checking, request preparation, and cache reference application.
     *
     * @param ChatCompletionRequest $chatRequest Original request
     * @return array{'geminiRequest': array, 'cacheWriteTokens': int}
     */
    private function prepareRequestWithCache(ChatCompletionRequest $chatRequest): array
    {
        $chatRequest->calculateTokenEstimates();

        // Step 1: Check cache to get cache info
        $cacheInfo = $this->checkCache($chatRequest);
        $cacheWriteTokens = 0;

        if ($cacheInfo && $cacheInfo->isNewlyCreated()) {
            $cacheWriteTokens = $cacheInfo->getCacheWriteTokens();
        }

        // Step 2: Prepare request for conversion (filter cached messages if needed)
        $this->prepareRequestForCache($chatRequest, $cacheInfo);

        // Step 3: Convert to Gemini native format
        $geminiRequest = RequestHandler::convertRequest($chatRequest);

        // Step 4: Apply cache reference if cache is available
        if ($cacheInfo) {
            $geminiRequest['cachedContent'] = $cacheInfo->getCacheName();
        }

        return [
            'geminiRequest' => $geminiRequest,
            'cacheWriteTokens' => $cacheWriteTokens,
        ];
    }

    /**
     * 判断是否使用 AI Platform API.
     *
     * @return bool true 表示使用 aiplatform.googleapis.com，false 表示使用 generativelanguage.googleapis.com
     */
    private function isAiPlatformApi(): bool
    {
        $baseUri = $this->getBaseUri();
        return str_contains($baseUri, 'aiplatform.googleapis.com');
    }

    /**
     * Build Gemini native API URL.
     */
    private function buildGeminiUrl(string $model, bool $stream): string
    {
        $baseUri = $this->getBaseUri();
        $endpoint = $stream ? 'streamGenerateContent' : 'generateContent';

        $url = "{$baseUri}/{$model}:{$endpoint}";

        if ($stream) {
            $url .= '?alt=sse';
        }

        return $url;
    }

    /**
     * Cache thought signatures from tool calls in the response.
     */
    private function cacheThoughtSignatures(ChatCompletionResponse $response): void
    {
        $firstChoice = $response->getFirstChoice();
        if ($firstChoice === null) {
            return;
        }

        $message = $firstChoice->getMessage();
        if (! $message instanceof AssistantMessage) {
            return;
        }

        $toolCalls = $message->getToolCalls();
        if (empty($toolCalls)) {
            return;
        }

        foreach ($toolCalls as $toolCall) {
            $thoughtSignature = $toolCall->getMetadata('thought_signature');
            if ($thoughtSignature !== null) {
                ThoughtSignatureCache::store($toolCall->getId(), $thoughtSignature);
            }
        }
    }
}

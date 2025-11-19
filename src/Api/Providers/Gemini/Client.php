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
                'content' => $chatResponse->getContent(),
                'usage' => $chatResponse->getUsage()?->toArray(),
                'response_headers' => $response->getHeaders(),
            ]);

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

            // Convert request to Gemini native format
            $geminiRequest = RequestHandler::convertRequest($chatRequest, $model);

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

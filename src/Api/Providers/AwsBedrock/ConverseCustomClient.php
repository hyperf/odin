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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AutoCacheConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AwsBedrockCachePointManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Exception\LLMException\Api\LLMRateLimitException;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Utils\EventUtil;
use Hyperf\Odin\Utils\LoggingConfigHelper;
use Hyperf\Odin\Utils\LogUtil;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Custom AWS Bedrock Converse Client using Guzzle HTTP without AWS SDK.
 */
class ConverseCustomClient extends AbstractClient
{
    protected AwsBedrockConfig $awsConfig;

    protected AwsSignatureV4 $signer;

    protected ConverterInterface $converter;

    protected string $endpoint;

    /**
     * Constructor.
     */
    public function __construct(AwsBedrockConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }

        $this->awsConfig = $config;
        $this->converter = $this->createConverter();
        $this->endpoint = $this->buildEndpoint();

        // Initialize AWS Signature V4 signer
        $this->signer = new AwsSignatureV4(
            $config->accessKey,
            $config->secretKey,
            $config->region
        );

        parent::__construct($config, $requestOptions, $logger);
    }

    /**
     * Chat completions (non-streaming).
     */
    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            // Get model ID and convert request parameters
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareConverseRequestBody($chatRequest);

            // Generate request ID
            $requestId = $this->generateRequestId();

            // Build URL
            $url = "{$this->endpoint}/model/{$modelId}/converse";

            // Convert binary bytes to base64 for JSON encoding
            $requestBodyForJson = $this->prepareBytesForJsonEncoding($requestBody);

            // Create PSR-7 request
            $request = new Request(
                'POST',
                $url,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode($requestBodyForJson, JSON_UNESCAPED_UNICODE)
            );

            // Sign the request
            $signedRequest = $this->signer->signRequest($request);

            // Log request
            $this->logger?->info('AwsBedrockConverseCustomRequest', LoggingConfigHelper::filterAndFormatLogData([
                'request_id' => $requestId,
                'model_id' => $modelId,
                'url' => $url,
                'body' => $requestBody,
                'token_estimate' => $chatRequest->getTokenEstimateDetail(),
            ], $this->requestOptions));

            // Send request with Guzzle
            $response = $this->client->send($signedRequest, $this->getGuzzleOptions(false));

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // milliseconds

            // Parse response
            $responseBody = json_decode($response->getBody()->getContents(), true);

            // Convert to PSR-7 standard Response
            $psrResponse = ResponseHandler::convertConverseToPsrResponse(
                $responseBody['output'] ?? [],
                $responseBody['usage'] ?? [],
                $chatRequest->getModel()
            );
            $chatCompletionResponse = new ChatCompletionResponse($psrResponse, $this->logger);

            $performanceFlag = LogUtil::getPerformanceFlag($duration);

            // Get message for logging
            $firstMessage = $chatCompletionResponse->getFirstChoice()?->getMessage();
            $messageContent = $firstMessage?->getContent();
            $reasoningContent = null;
            if ($firstMessage instanceof AssistantMessage) {
                $reasoningContent = $firstMessage->getReasoningContent();
            }

            $logData = [
                'request_id' => $requestId,
                'model_id' => $modelId,
                'duration_ms' => $duration,
                'usage' => $responseBody['usage'] ?? [],
                'converted_usage' => $chatCompletionResponse->getUsage()->toArray(),
                'cache_hit_rate' => $chatCompletionResponse->getUsage()->getCacheHitRatePercentage(),
                'message_content' => $messageContent,  // 只记录消息内容，不是整个响应
                'reasoning_content' => $reasoningContent,  // 记录思考内容
                'response_headers' => $response->getHeaders(),
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('AwsBedrockConverseCustomResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatCompletionResponse, $duration));

            return $chatCompletionResponse;
        } catch (GuzzleException $e) {
            throw $this->convertGuzzleException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    /**
     * Chat completions (streaming).
     */
    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            // Get model ID and convert request parameters
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareConverseRequestBody($chatRequest);
            $requestId = $this->generateRequestId();

            // Build streaming URL
            $url = "{$this->endpoint}/model/{$modelId}/converse-stream";

            // Convert binary bytes to base64 for JSON encoding
            $requestBodyForJson = $this->prepareBytesForJsonEncoding($requestBody);

            // Create PSR-7 request for streaming
            $request = new Request(
                'POST',
                $url,
                [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/vnd.amazon.eventstream',
                ],
                json_encode($requestBodyForJson, JSON_UNESCAPED_UNICODE)
            );

            // Sign the request
            $signedRequest = $this->signer->signRequest($request);

            // Log request
            $this->logger?->info('AwsBedrockConverseCustomStreamRequest', LoggingConfigHelper::filterAndFormatLogData([
                'request_id' => $requestId,
                'model_id' => $modelId,
                'url' => $url,
                'body' => $requestBody,
                'token_estimate' => $chatRequest->getTokenEstimateDetail(),
            ], $this->requestOptions));

            // Send streaming request
            $response = $this->client->send($signedRequest, $this->getGuzzleOptions(true));

            $firstResponseTime = microtime(true);
            $firstResponseDuration = round(($firstResponseTime - $startTime) * 1000); // milliseconds

            // Log first response
            $performanceFlag = LogUtil::getPerformanceFlag($firstResponseDuration);
            $logData = [
                'request_id' => $requestId,
                'model_id' => $modelId,
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $response->getHeaders(),
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('AwsBedrockConverseCustomStreamFirstResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            // Create custom stream converter (no AWS SDK dependency)
            $streamConverter = new CustomConverseStreamConverter($response, $this->logger, $modelId);

            $chatCompletionStreamResponse = new ChatCompletionStreamResponse(
                logger: $this->logger,
                streamIterator: $streamConverter
            );
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent(
                new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration)
            );

            return $chatCompletionStreamResponse;
        } catch (GuzzleException $e) {
            throw $this->convertGuzzleException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    /**
     * Embeddings (not implemented for Bedrock Converse).
     */
    public function embeddings(EmbeddingRequest $embeddingRequest): EmbeddingResponse
    {
        throw new RuntimeException('Embeddings are not supported by Bedrock Converse API');
    }

    /**
     * Build AWS Bedrock endpoint URL.
     */
    protected function buildEndpoint(): string
    {
        return sprintf('https://bedrock-runtime.%s.amazonaws.com', $this->awsConfig->region);
    }

    /**
     * Build chat completions URL (required by AbstractClient).
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->endpoint;
    }

    /**
     * Build embeddings URL (required by AbstractClient).
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->endpoint;
    }

    /**
     * Build completions URL (required by AbstractClient).
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->endpoint;
    }

    /**
     * Get auth headers (not used as we use AWS Signature V4).
     */
    protected function getAuthHeaders(): array
    {
        return [];
    }

    /**
     * Create converter for message transformation.
     */
    protected function createConverter(): ConverterInterface
    {
        return new ConverseConverter();
    }

    /**
     * Get Guzzle options for request.
     */
    protected function getGuzzleOptions(bool $stream = false): array
    {
        $options = [
            'timeout' => $this->requestOptions->getTotalTimeout(),  // Use total timeout (number)
            'connect_timeout' => $this->requestOptions->getConnectionTimeout(),  // Connection timeout
            'http_errors' => true,  // Enable exceptions for 4xx and 5xx responses
        ];

        if ($stream) {
            $options['stream'] = true;
        }

        if ($proxy = $this->requestOptions->getProxy()) {
            $options['proxy'] = $proxy;
        }

        // SSL/TLS options - verify certificates by default
        // Set verify to false only in development if needed (not recommended)
        $options['verify'] = true;

        // Add debug option if needed (helps troubleshoot connection issues)
        // $options['debug'] = true;  // Uncomment to see detailed debug output

        return $options;
    }

    /**
     * Convert Guzzle exception to LLM exception.
     */
    protected function convertGuzzleException(GuzzleException $e): LLMException
    {
        $message = $e->getMessage();
        $code = (int) $e->getCode();

        // Get response body if available (for BadResponseException)
        if ($e instanceof BadResponseException) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            try {
                $jsonBody = json_decode($responseBody, true);
                if (isset($jsonBody['message'])) {
                    $message = $jsonBody['message'];
                }
            } catch (Throwable $jsonException) {
                // Ignore JSON parse errors
            }

            // Map HTTP status codes to LLM exceptions
            if ($statusCode === 429) {
                return new LLMRateLimitException($message, $e, $statusCode);
            }

            if ($statusCode >= 400 && $statusCode < 500) {
                return new LLMInvalidRequestException($message, $e, $statusCode);
            }

            if ($statusCode >= 500) {
                return new LLMApiException($message, $statusCode, $e, 0, $statusCode);
            }
        }

        // Check for timeout
        if (str_contains($message, 'timed out')) {
            return new LLMReadTimeoutException($message, $e);
        }

        return new LLMApiException($message, $code, $e);
    }

    /**
     * Convert general exception to LLM exception.
     */
    protected function convertException(Throwable $exception, array $context = []): LLMException
    {
        $message = $exception->getMessage();
        $code = (int) $exception->getCode();

        // Check for timeout
        if (str_contains($message, 'timed out')) {
            return new LLMReadTimeoutException($message, $exception);
        }

        // Check for rate limit
        if (str_contains($message, 'rate limit') || str_contains($message, 'throttled')) {
            return new LLMRateLimitException($message, $exception, $code);
        }

        // Check for client errors
        if ($code >= 400 && $code < 500) {
            return new LLMInvalidRequestException($message, $exception, $code);
        }

        // Check for server errors
        if ($code >= 500) {
            return new LLMApiException($message, $code, $exception, 0, $code);
        }

        // Default to generic API exception
        return new LLMApiException($message, $code, $exception);
    }

    /**
     * Check if auto cache is enabled.
     */
    protected function isAutoCache(): bool
    {
        return $this->awsConfig->isAutoCache();
    }

    /**
     * Get auto cache configuration.
     */
    protected function getAutoCacheConfig(): AutoCacheConfig
    {
        return $this->awsConfig->getAutoCacheConfig();
    }

    /**
     * Prepare bytes fields for JSON encoding by converting binary data to base64.
     * This is necessary because AWS Bedrock API expects base64-encoded strings for bytes fields,
     * while the converter returns binary data (for AWS SDK compatibility).
     *
     * @param array $data Request body data
     * @return array Data with bytes fields converted to base64
     */
    private function prepareBytesForJsonEncoding(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Recursively process nested arrays
                $data[$key] = $this->prepareBytesForJsonEncoding($value);
            } elseif ($key === 'bytes' && is_string($value)) {
                // Convert binary bytes to base64 string for JSON encoding
                // Check if it's already base64 (printable ASCII) or binary
                if (! ctype_print($value) || strlen($value) !== strlen(utf8_decode($value))) {
                    $data[$key] = base64_encode($value);
                }
            }
        }

        return $data;
    }

    /**
     * Prepare Converse API request body.
     */
    private function prepareConverseRequestBody(ChatCompletionRequest $chatRequest): array
    {
        if ($this->isAutoCache()) {
            $cachePointManager = new AwsBedrockCachePointManager($this->getAutoCacheConfig());
            $cachePointManager->configureCachePoints($chatRequest);
        }

        $messages = [];
        $systemMessage = '';
        $originalMessages = $chatRequest->getMessages();

        // Process messages with tool call grouping logic
        $processedMessages = $this->processMessagesWithToolGrouping($originalMessages);

        foreach ($processedMessages as $message) {
            if (! $message instanceof MessageInterface) {
                continue;
            }
            match (true) {
                $message instanceof SystemMessage => $systemMessage = $this->converter->convertSystemMessage($message),
                $message instanceof ToolMessage => $messages[] = $this->converter->convertToolMessage($message),
                $message instanceof AssistantMessage => $messages[] = $this->converter->convertAssistantMessage($message),
                $message instanceof UserMessage => $messages[] = $this->converter->convertUserMessage($message),
            };
        }

        // Get request parameters
        $maxTokens = $chatRequest->getMaxTokens();
        $temperature = $chatRequest->getTemperature();
        $stop = $chatRequest->getStop();

        // Prepare request body - conform to Converse API format
        $requestBody = [
            'messages' => $messages,
        ];

        // Add system prompt
        if (! empty($systemMessage)) {
            $requestBody['system'] = $systemMessage;
        }

        // Add inference configuration
        $inferenceConfig = [
            'temperature' => $temperature,
        ];

        // Add max tokens
        if ($maxTokens > 0) {
            $inferenceConfig['maxTokens'] = $maxTokens;
        }

        // Add inference config if not empty
        if (! empty($inferenceConfig)) {
            $requestBody['inferenceConfig'] = $inferenceConfig;
        }

        // Add stop sequences
        if (! empty($stop)) {
            $requestBody['additionalModelRequestFields'] = [
                'stop_sequences' => $stop,
            ];
        }

        if (! empty($chatRequest->getThinking())) {
            $requestBody['thinking'] = $chatRequest->getThinking();
        }

        // Add tool support
        if (! empty($chatRequest->getTools())) {
            $tools = $this->converter->convertTools($chatRequest->getTools(), $chatRequest->isToolsCache());
            if (! empty($tools)) {
                $requestBody['toolConfig'] = [
                    'tools' => $tools,
                ];
            }
        }

        return $requestBody;
    }

    /**
     * Process messages and group tool results for multi-tool calls.
     *
     * When an AssistantMessage contains multiple tool calls, Claude's Converse API
     * requires all corresponding tool results to be in the same user message.
     *
     * @param array $messages Original messages array
     * @return array Processed messages with grouped tool results
     */
    private function processMessagesWithToolGrouping(array $messages): array
    {
        $processedMessages = [];
        $messageCount = count($messages);

        for ($i = 0; $i < $messageCount; ++$i) {
            $message = $messages[$i];

            // Add non-assistant messages as-is
            if (! $message instanceof AssistantMessage) {
                $processedMessages[] = $message;
                continue;
            }

            // Add the assistant message
            $processedMessages[] = $message;

            // Check if this assistant message has multiple tool calls
            if (! $message->hasToolCalls() || count($message->getToolCalls()) <= 1) {
                continue;
            }

            // Collect the expected tool call IDs
            $expectedToolIds = [];
            foreach ($message->getToolCalls() as $toolCall) {
                $expectedToolIds[] = $toolCall->getId();
            }

            // Look for consecutive tool messages that match the expected tool IDs
            $collectedToolMessages = [];
            $j = $i + 1;

            while ($j < $messageCount && $messages[$j] instanceof ToolMessage) {
                $toolMessage = $messages[$j];
                $toolCallId = $toolMessage->getToolCallId();

                // Check if this tool message belongs to the current assistant message
                if (in_array($toolCallId, $expectedToolIds)) {
                    $collectedToolMessages[] = $toolMessage;
                    ++$j;
                } else {
                    // This tool message doesn't belong to current assistant message
                    break;
                }
            }

            // If we found multiple tool messages, merge them
            if (count($collectedToolMessages) > 1) {
                $mergedToolMessage = $this->createMergedToolMessage($collectedToolMessages);
                $processedMessages[] = $mergedToolMessage;
                // Skip the original tool messages since we've merged them
                $i = $j - 1;
            }
        }

        return $processedMessages;
    }

    /**
     * Create a merged tool message from multiple tool messages.
     *
     * @param array $toolMessages Array of ToolMessage instances
     * @return ToolMessage Merged tool message
     */
    private function createMergedToolMessage(array $toolMessages): ToolMessage
    {
        return new MergedToolMessage($toolMessages);
    }
}

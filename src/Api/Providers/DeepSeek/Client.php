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

namespace Hyperf\Odin\Api\Providers\DeepSeek;

use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Message\AssistantMessage;
use Psr\Log\LoggerInterface;

/**
 * DeepSeek API client.
 *
 * Supports both standard chat and reasoning (thinking) mode.
 * When thinking mode is enabled, the response will include reasoning_content
 * which represents the model's internal thinking process.
 *
 * Key behaviors for thinking mode:
 * - During tool calling within a single question, reasoning_content is preserved and cached
 * - When continuing a tool calling sequence, cached reasoning_content is restored to messages
 * - When a new question starts, previous reasoning_content is not needed
 *
 * @see https://api-docs.deepseek.com/zh-cn/guides/thinking_mode
 */
class Client extends AbstractClient
{
    protected DeepSeekConfig $deepSeekConfig;

    public function __construct(DeepSeekConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        $this->deepSeekConfig = $config;
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }
        parent::__construct($config, $requestOptions, $logger);
    }

    /**
     * Chat completions with thinking mode support.
     */
    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $this->restoreReasoningContentFromCache($chatRequest);
        $response = parent::chatCompletions($chatRequest);
        $this->cacheReasoningContentFromResponse($response);
        return $response;
    }

    /**
     * Chat completions stream with thinking mode support.
     */
    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $this->restoreReasoningContentFromCache($chatRequest);
        $response = parent::chatCompletionsStream($chatRequest);

        // Add callback to cache reasoning_content after stream completion
        /** @var AfterChatCompletionsStreamEvent $event */
        $event = $response->getAfterChatCompletionsStreamEvent();
        $event?->addCallback(function ($event) {
            $this->cacheReasoningContentFromResponse($event->completionResponse);
        });

        return $response;
    }

    /**
     * Build the chat completions API URL.
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/chat/completions';
    }

    /**
     * Build the embeddings API URL.
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->getBaseUri() . '/embeddings';
    }

    /**
     * Build the completions API URL.
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/completions';
    }

    /**
     * Get authentication headers.
     */
    protected function getAuthHeaders(): array
    {
        $headers = [];

        if ($this->deepSeekConfig->getApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $this->deepSeekConfig->getApiKey();
        }

        return $headers;
    }

    /**
     * Restore reasoning_content from cache for assistant messages that have tool calls.
     *
     * When continuing a tool calling sequence, the assistant messages may not have
     * reasoning_content set (e.g., when messages are constructed from external sources).
     * This method restores the cached reasoning_content based on tool_call_id.
     */
    private function restoreReasoningContentFromCache(ChatCompletionRequest $chatRequest): void
    {
        $messages = $chatRequest->getMessages();
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $message) {
            // Only process assistant messages with tool calls but without reasoning_content
            if ($message instanceof AssistantMessage
                && $message->hasToolCalls()
                && ! $message->hasReasoningContent()) {
                // Try to restore reasoning_content from cache using tool_call_id
                foreach ($message->getToolCalls() as $toolCall) {
                    $cachedReasoningContent = ReasoningContentCache::get($toolCall->getId());
                    if ($cachedReasoningContent !== null) {
                        $message->setReasoningContent($cachedReasoningContent);
                        break; // Only need to restore once per assistant message
                    }
                }
            }
        }
    }

    /**
     * Cache reasoning_content from response.
     *
     * When the response contains tool calls with reasoning_content,
     * cache the reasoning_content using tool_call_id as the key.
     * This allows restoring reasoning_content in subsequent requests.
     */
    private function cacheReasoningContentFromResponse(ChatCompletionResponse $response): void
    {
        $choice = $response->getFirstChoice();
        if ($choice === null) {
            return;
        }

        $message = $choice->getMessage();
        if (! $message instanceof AssistantMessage) {
            return;
        }

        // Only cache if there's reasoning_content and tool calls
        if (! $message->hasReasoningContent() || ! $message->hasToolCalls()) {
            return;
        }

        $reasoningContent = $message->getReasoningContent();
        if ($reasoningContent === null) {
            return;
        }

        // Cache reasoning_content for each tool call
        foreach ($message->getToolCalls() as $toolCall) {
            ReasoningContentCache::store($toolCall->getId(), $reasoningContent);
            break; // Only need to cache once per assistant message
        }
    }
}

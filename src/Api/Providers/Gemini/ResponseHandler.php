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

use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Api\Response\Usage;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Response Handler for converting Gemini native format to OpenAI format.
 */
class ResponseHandler
{
    /**
     * Convert Gemini response to PSR-7 Response in OpenAI format.
     *
     * @param array $geminiResponse Gemini native response
     * @param string $model Model name
     * @param int $cacheWriteTokens Tokens written to cache (0 if no cache created)
     * @param string $contextHash Context hash for thought signature caching
     */
    public static function convertResponse(array $geminiResponse, string $model, int $cacheWriteTokens = 0, string $contextHash = ''): ResponseInterface
    {
        $openAIResponse = [
            'id' => self::generateId(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'choices' => self::convertCandidates($geminiResponse['candidates'] ?? [], $contextHash),
            'usage' => self::convertUsage($geminiResponse['usageMetadata'] ?? [], $cacheWriteTokens),
        ];

        $jsonResponse = json_encode($openAIResponse);

        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            $jsonResponse
        );
    }

    /**
     * Convert Gemini candidates to OpenAI choices format.
     *
     * @param array $candidates Gemini candidates
     * @param string $contextHash Context hash for thought signature caching
     */
    private static function convertCandidates(array $candidates, string $contextHash = ''): array
    {
        $choices = [];

        foreach ($candidates as $index => $candidate) {
            $content = $candidate['content'] ?? [];
            $message = self::convertContent($content, $contextHash);

            // Determine finish reason
            // If there are tool calls, finish_reason should be 'tool_calls'
            $finishReason = $candidate['finishReason'] ?? 'STOP';

            // Check for tool calls first
            $hasToolCalls = ! empty($message['tool_calls']);

            // Log warning if finishMessage is present and it's not the expected tool call message
            // Note: "Model generated function call(s)." is a normal message when tool calls are present
            if (isset($candidate['finishMessage'])) {
                $isNormalToolCallMessage = $hasToolCalls
                    && $candidate['finishMessage'] === 'Model generated function call(s).';

                if (! $isNormalToolCallMessage) {
                    // Only log if it's an unexpected finish message
                    error_log(sprintf(
                        'Gemini response warning [finish_reason=%s, index=%d]: %s',
                        $finishReason,
                        $index,
                        $candidate['finishMessage']
                    ));
                }
            }

            if ($hasToolCalls) {
                $finishReason = 'tool_calls';
            } else {
                $finishReason = self::convertFinishReason($finishReason);
            }

            $choices[] = [
                'index' => $index,
                'message' => $message,
                'finish_reason' => $finishReason,
            ];
        }

        return $choices;
    }

    /**
     * Convert Gemini content to OpenAI message format.
     *
     * @param array $content Gemini content
     * @param string $contextHash Context hash for thought signature caching
     */
    private static function convertContent(array $content, string $contextHash = ''): array
    {
        $message = [
            'role' => 'assistant', // Gemini uses 'model', convert to 'assistant'
        ];

        $parts = $content['parts'] ?? [];
        $textParts = [];
        $thoughtParts = [];
        $toolCalls = [];
        $thoughtSignature = null;

        foreach ($parts as $part) {
            // Check if this is a thought part
            $isThought = isset($part['thought']) && $part['thought'] === true;

            // Handle text parts
            if (isset($part['text'])) {
                if ($isThought) {
                    // This is thinking/reasoning content
                    $thoughtParts[] = $part['text'];
                } else {
                    // This is normal response content
                    $textParts[] = $part['text'];
                }
            }

            // Capture thoughtSignature from any part (can be on text part or standalone)
            if (isset($part['thoughtSignature'])) {
                $thoughtSignature = $part['thoughtSignature'];
            }

            // Handle function calls (tool calls)
            if (isset($part['functionCall'])) {
                $functionCall = $part['functionCall'];
                $args = $functionCall['args'] ?? new stdClass();

                // Convert args to JSON string (OpenAI format)
                $argumentsJson = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $toolCall = [
                    'id' => self::generateToolCallId(),
                    'type' => 'function',
                    'function' => [
                        'name' => $functionCall['name'] ?? '',
                        'arguments' => $argumentsJson,
                    ],
                ];

                // Preserve thought signature if present (Gemini-specific)
                // thoughtSignature is at part level, not inside functionCall
                // This is required for Gemini 3 Pro multi-turn function calling
                if (isset($part['thoughtSignature'])) {
                    $toolCall['thought_signature'] = $part['thoughtSignature'];
                }

                $toolCalls[] = $toolCall;
            }
        }

        // Combine text parts
        $message['content'] = implode('', $textParts);

        // Add reasoning content if present
        if (! empty($thoughtParts)) {
            $message['reasoning_content'] = implode("\n\n", $thoughtParts);
        }

        // Add tool calls if present
        if (! empty($toolCalls)) {
            $message['tool_calls'] = $toolCalls;
        }

        // Store thought signature for normal messages (without tool calls)
        // For tool calls, the signature is stored per tool call
        if ($thoughtSignature !== null && empty($toolCalls) && $contextHash !== '') {
            // Use context hash + content hash as cache key for uniqueness
            $cacheKey = ThoughtSignatureCache::generateMessageKey($contextHash, $message['content']);
            ThoughtSignatureCache::store($cacheKey, $thoughtSignature);
        }

        return $message;
    }

    /**
     * Convert Gemini usage metadata to OpenAI usage format.
     *
     * @param array $usageMetadata Gemini usage metadata
     * @param int $cacheWriteTokens Tokens written to cache in this request (0 if no cache created)
     */
    private static function convertUsage(array $usageMetadata, int $cacheWriteTokens = 0): array
    {
        // Gemini format:
        // - promptTokenCount: tokens from new input (not from cache)
        // - cachedContentTokenCount: tokens read from cache
        $cacheReadTokens = $usageMetadata['cachedContentTokenCount'] ?? 0;
        $inputTokens = $usageMetadata['promptTokenCount'] ?? 0 - $cacheReadTokens;

        // 如果有 $cacheWriteTokens，代表是第一次写入，那么本次虽然会读取到缓存但是不计入读取量
        if ($cacheWriteTokens > 0) {
            $cacheReadTokens = 0;
        }

        // OpenAI format: prompt_tokens = total prompt tokens (including cache)
        // Following AWS Bedrock's implementation for consistency
        $promptTokens = $inputTokens + $cacheReadTokens + $cacheWriteTokens;

        $candidatesTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
        $thoughtsTokens = $usageMetadata['thoughtsTokenCount'] ?? 0;

        // completion_tokens includes both candidates tokens and thoughts tokens for billing
        $completionTokens = $candidatesTokens + $thoughtsTokens;

        // total_tokens = prompt_tokens + completion_tokens
        $totalTokens = $promptTokens + $completionTokens;

        $usage = [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];

        // Build prompt_tokens_details
        $promptTokensDetails = [];

        // Add cached tokens if present (Gemini Context Caching - cache read)
        if ($cacheReadTokens > 0) {
            $promptTokensDetails['cached_tokens'] = $cacheReadTokens;
            $promptTokensDetails['cache_read_input_tokens'] = $cacheReadTokens;
        }

        // Add cache write tokens if present (cache created in this request)
        if ($cacheWriteTokens > 0) {
            $promptTokensDetails['cache_write_input_tokens'] = $cacheWriteTokens;
        }

        // Add prompt_tokens_details if not empty
        if (! empty($promptTokensDetails)) {
            $usage['prompt_tokens_details'] = $promptTokensDetails;
        }

        // Build completion_tokens_details if thoughts tokens are present
        // Record reasoning tokens separately for transparency (but already included in completion_tokens)
        if ($thoughtsTokens > 0) {
            $usage['completion_tokens_details'] = [
                'reasoning_tokens' => $thoughtsTokens,
            ];
        }

        return $usage;
    }

    /**
     * Convert Gemini finish reason to OpenAI format.
     */
    private static function convertFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY', 'RECITATION' => 'content_filter',
            'MALFORMED_FUNCTION_CALL' => 'stop', // Tool call format error, treated as stop but logged as error
            'OTHER' => 'stop',
            default => 'stop',
        };
    }

    /**
     * Generate a unique ID for the response.
     */
    private static function generateId(): string
    {
        return 'chatcmpl-' . bin2hex(random_bytes(12));
    }

    /**
     * Generate a unique tool call ID.
     */
    private static function generateToolCallId(): string
    {
        return 'call_' . bin2hex(random_bytes(12));
    }
}

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
     */
    public static function convertResponse(array $geminiResponse, string $model): ResponseInterface
    {
        $openAIResponse = [
            'id' => self::generateId(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'choices' => self::convertCandidates($geminiResponse['candidates'] ?? []),
            'usage' => self::convertUsage($geminiResponse['usageMetadata'] ?? []),
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
     */
    private static function convertCandidates(array $candidates): array
    {
        $choices = [];

        foreach ($candidates as $index => $candidate) {
            $content = $candidate['content'] ?? [];
            $message = self::convertContent($content);

            // Add reasoning content if present (from thinking)
            if (isset($candidate['thinkingTrace'])) {
                $message['reasoning_content'] = self::extractThinkingContent($candidate['thinkingTrace']);
            }

            // Determine finish reason
            // If there are tool calls, finish_reason should be 'tool_calls'
            $finishReason = $candidate['finishReason'] ?? 'STOP';

            // Log error if finishMessage is present (indicates an error occurred)
            if (isset($candidate['finishMessage'])) {
                error_log(sprintf(
                    'Gemini response error [finish_reason=%s, index=%d]: %s',
                    $finishReason,
                    $index,
                    $candidate['finishMessage']
                ));
            }

            if (! empty($message['tool_calls'])) {
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
     */
    private static function convertContent(array $content): array
    {
        $message = [
            'role' => 'assistant', // Gemini uses 'model', convert to 'assistant'
        ];

        $parts = $content['parts'] ?? [];
        $textParts = [];
        $toolCalls = [];

        foreach ($parts as $part) {
            // Handle text parts
            if (isset($part['text'])) {
                $textParts[] = $part['text'];
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
                // This is required for Gemini 3 Pro multi-turn function calling
                if (isset($functionCall['thoughtSignature'])) {
                    $toolCall['thought_signature'] = $functionCall['thoughtSignature'];
                }

                $toolCalls[] = $toolCall;
            }
        }

        // Combine text parts
        $message['content'] = implode('', $textParts);

        // Add tool calls if present
        if (! empty($toolCalls)) {
            $message['tool_calls'] = $toolCalls;
        }

        return $message;
    }

    /**
     * Convert Gemini usage metadata to OpenAI usage format.
     */
    private static function convertUsage(array $usageMetadata): array
    {
        $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
        $completionTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
        $totalTokens = $usageMetadata['totalTokenCount'] ?? ($promptTokens + $completionTokens);

        $usage = [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];

        // Add cached tokens if present (Gemini Context Caching)
        if (isset($usageMetadata['cachedContentTokenCount'])) {
            $usage['prompt_tokens_details'] = [
                'cached_tokens' => $usageMetadata['cachedContentTokenCount'],
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
     * Extract thinking content from thinkingTrace.
     */
    private static function extractThinkingContent(array $thinkingTrace): string
    {
        $thoughts = [];

        foreach ($thinkingTrace as $trace) {
            if (isset($trace['thought'])) {
                $thoughts[] = $trace['thought'];
            }
        }

        return implode("\n", $thoughts);
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

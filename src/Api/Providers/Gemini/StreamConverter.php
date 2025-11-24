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

use Generator;
use IteratorAggregate;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use stdClass;
use Traversable;

/**
 * Stream Converter for converting Gemini streaming response to OpenAI format.
 */
class StreamConverter implements IteratorAggregate
{
    private ResponseInterface $response;

    private ?LoggerInterface $logger;

    private string $model;

    /**
     * Track tool calls by candidate index and tool call index.
     * Structure: [candidateIndex => [toolCallIndex => [
     *   'id' => string,
     *   'name' => string,
     *   'args' => string,
     *   'args_array' => array,
     *   'is_complete' => bool,
     *   'chunk_count' => int
     * ]]].
     */
    private array $toolCallTracker = [];

    /**
     * Track whether each candidate has had tool calls.
     * Used to determine correct finish_reason when finishReason arrives.
     * Structure: [candidateIndex => bool].
     */
    private array $candidateHasToolCalls = [];

    /**
     * Strategy for handling function call arguments in streaming mode.
     * - 'complete': Each chunk contains complete args (Gemini's current behavior)
     * - 'incremental': Each chunk contains partial args that need to be merged
     * - 'auto': Automatically detect based on args changes.
     */
    private string $argsStrategy = 'auto';

    private int $cacheWriteTokens;

    public function __construct(
        ResponseInterface $response,
        ?LoggerInterface $logger,
        string $model,
        int $cacheWriteTokens = 0
    ) {
        $this->response = $response;
        $this->logger = $logger;
        $this->model = $model;
        $this->cacheWriteTokens = $cacheWriteTokens;
    }

    /**
     * Get iterator for streaming chunks.
     */
    public function getIterator(): Traversable
    {
        return $this->parseStream();
    }

    /**
     * Parse streaming response and convert to OpenAI format.
     */
    private function parseStream(): Generator
    {
        $stream = $this->response->getBody();
        $buffer = '';
        $chunkCount = 0;

        $this->logger?->info('GeminiStreamProcessingStarted', [
            'model' => $this->model,
        ]);

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // Process complete JSON objects in buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                // Skip empty lines
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                // Remove data: prefix if present (SSE format)
                if (str_starts_with($line, 'data: ')) {
                    $line = substr($line, 6);
                }

                // Check for done signal
                if ($line === '[DONE]') {
                    $this->logger?->info('GeminiStreamCompleted', [
                        'total_chunks' => $chunkCount,
                    ]);
                    break 2;
                }

                try {
                    $geminiChunk = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                    // Convert Gemini chunk to OpenAI format
                    $openAIChunk = $this->convertStreamChunk($geminiChunk);

                    if ($openAIChunk !== null) {
                        ++$chunkCount;
                        yield $openAIChunk;
                    }
                } catch (JsonException $e) {
                    $this->logger?->warning('GeminiStreamJsonDecodeError', [
                        'error' => $e->getMessage(),
                        'line' => substr($line, 0, 200),
                    ]);
                    continue;
                }
            }
        }

        $this->logger?->info('GeminiStreamFinished', [
            'total_chunks' => $chunkCount,
        ]);

        // Cache thought signatures from completed tool calls
        $this->cacheThoughtSignatures();
    }

    /**
     * Convert a single Gemini stream chunk to OpenAI format.
     */
    private function convertStreamChunk(array $geminiChunk): ?array
    {
        $candidates = $geminiChunk['candidates'] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $choices = [];
        foreach ($candidates as $index => $candidate) {
            $delta = $this->convertDelta($candidate['content'] ?? [], $index);

            $choice = [
                'index' => $index,
                'delta' => $delta,
                'finish_reason' => null,
            ];

            // Add finish reason if present
            if (isset($candidate['finishReason'])) {
                $finishReason = $candidate['finishReason'];

                // Check if this candidate has tool calls
                $hasToolCalls = ! empty($delta['tool_calls']) || ! empty($this->candidateHasToolCalls[$index]);

                // Log warning if finishMessage is present, and it's not the expected tool call message
                // Note: "Model generated function call(s)." is a normal message when tool calls are present
                if (isset($candidate['finishMessage'])) {
                    $isNormalToolCallMessage = $hasToolCalls
                        && $candidate['finishMessage'] === 'Model generated function call(s).';

                    if (! $isNormalToolCallMessage) {
                        // Only log if it's an unexpected finish message
                        $this->logger?->warning('GeminiStreamFinishWithError', [
                            'finish_reason' => $finishReason,
                            'finish_message' => $candidate['finishMessage'],
                            'candidate_index' => $index,
                        ]);
                    }
                }

                // If there are tool calls in current delta OR this candidate has had tool calls before,
                // finish_reason should be 'tool_calls'
                if ($hasToolCalls) {
                    $choice['finish_reason'] = 'tool_calls';
                } else {
                    $choice['finish_reason'] = $this->convertFinishReason($finishReason);
                }
            }

            $choices[] = $choice;
        }

        $chunk = [
            'id' => 'chatcmpl-' . bin2hex(random_bytes(12)),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $this->model,
            'choices' => $choices,
        ];

        // Add usage if present (final chunk)
        if (isset($geminiChunk['usageMetadata'])) {
            $chunk['usage'] = $this->convertUsage($geminiChunk['usageMetadata']);
        }

        return $chunk;
    }

    /**
     * Convert Gemini content to OpenAI delta format.
     *
     * @param array $content Gemini content
     * @param int $candidateIndex Candidate index for tracking tool calls
     */
    private function convertDelta(array $content, int $candidateIndex): array
    {
        $delta = [];
        $parts = $content['parts'] ?? [];

        // Initialize tracker for this candidate if not exists
        if (! isset($this->toolCallTracker[$candidateIndex])) {
            $this->toolCallTracker[$candidateIndex] = [];
        }

        // Initialize candidateHasToolCalls flag if not exists
        if (! isset($this->candidateHasToolCalls[$candidateIndex])) {
            $this->candidateHasToolCalls[$candidateIndex] = false;
        }

        foreach ($parts as $part) {
            // Handle text delta
            if (isset($part['text'])) {
                if (! isset($delta['content'])) {
                    $delta['content'] = '';
                }
                $delta['content'] .= $part['text'];
            }

            // Handle function call delta
            if (isset($part['functionCall'])) {
                if (! isset($delta['tool_calls'])) {
                    $delta['tool_calls'] = [];
                }

                // Pass the entire part (which includes thoughtSignature if present)
                $toolCallDelta = $this->processFunctionCall(
                    $part,
                    $candidateIndex
                );

                if ($toolCallDelta !== null) {
                    $delta['tool_calls'][] = $toolCallDelta;
                    // Mark that this candidate has tool calls
                    $this->candidateHasToolCalls[$candidateIndex] = true;
                }
            }
        }

        // Set role on first chunk
        if (empty($delta)) {
            $delta['role'] = 'assistant';
        }

        return $delta;
    }

    /**
     * Convert Gemini usage metadata to OpenAI usage format.
     */
    private function convertUsage(array $usageMetadata): array
    {
        // Gemini format:
        // - promptTokenCount: tokens from new input (not from cache)
        // - cachedContentTokenCount: tokens read from cache
        $inputTokens = $usageMetadata['promptTokenCount'] ?? 0;
        $cacheReadTokens = $usageMetadata['cachedContentTokenCount'] ?? 0;

        // OpenAI format: prompt_tokens = total prompt tokens (including cache)
        // Following AWS Bedrock's implementation for consistency
        $promptTokens = $inputTokens + $cacheReadTokens + $this->cacheWriteTokens;

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
        if ($this->cacheWriteTokens > 0) {
            $promptTokensDetails['cache_write_input_tokens'] = $this->cacheWriteTokens;
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
    private function convertFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'STOP' => 'stop',
            'MAX_TOKENS' => 'length',
            'SAFETY', 'RECITATION' => 'content_filter',
            'MALFORMED_FUNCTION_CALL' => 'stop', // Tool call format error, treated as stop but logged as warning
            'OTHER' => 'stop',
            default => 'stop',
        };
    }

    /**
     * Process a function call from Gemini stream chunk.
     * Handles both complete and incremental argument updates intelligently.
     *
     * @param int $candidateIndex Candidate index for tracking
     * @return null|array The tool call delta in OpenAI format, or null if invalid
     */
    private function processFunctionCall(array $part, int $candidateIndex): ?array
    {
        // Extract functionCall from part
        $functionCall = $part['functionCall'] ?? [];
        $functionName = $functionCall['name'] ?? '';
        if ($functionName === '') {
            $this->logger?->warning('GeminiStreamFunctionCallMissingName', [
                'part' => $part,
            ]);
            return null;
        }

        $functionArgs = $functionCall['args'] ?? new stdClass();

        // Find or create tool call tracker
        $toolCallIndex = $this->findOrCreateToolCall($candidateIndex, $functionName);

        // Process and merge arguments based on strategy
        $mergedArgs = $this->mergeArguments(
            $candidateIndex,
            $toolCallIndex,
            $functionArgs
        );

        // Extract thoughtSignature from part (it's at the same level as functionCall in Gemini response)
        $thoughtSignature = $part['thoughtSignature'] ?? null;

        // Store thought signature in tracker if present (for caching later)
        if ($thoughtSignature !== null) {
            $this->toolCallTracker[$candidateIndex][$toolCallIndex]['thought_signature'] = $thoughtSignature;
        }

        // Build tool call delta
        $toolCallDelta = [
            'index' => $toolCallIndex,
            'id' => $this->toolCallTracker[$candidateIndex][$toolCallIndex]['id'],
            'type' => 'function',
            'function' => [
                'name' => $functionName,
                'arguments' => $mergedArgs,
            ],
        ];

        // Preserve thought signature if present (Gemini-specific)
        // Required for Gemini 3 Pro multi-turn function calling
        if ($thoughtSignature !== null) {
            $toolCallDelta['thought_signature'] = $thoughtSignature;
        }

        return $toolCallDelta;
    }

    /**
     * Find existing tool call or create a new one.
     *
     * @param int $candidateIndex Candidate index
     * @param string $functionName Function name
     * @return int Tool call index
     */
    private function findOrCreateToolCall(int $candidateIndex, string $functionName): int
    {
        // Find existing tool call by name
        foreach ($this->toolCallTracker[$candidateIndex] as $idx => $tracked) {
            if ($tracked['name'] === $functionName) {
                return $idx;
            }
        }

        // Create new tool call
        $toolCallIndex = count($this->toolCallTracker[$candidateIndex]);
        $this->toolCallTracker[$candidateIndex][$toolCallIndex] = [
            'id' => 'call_' . bin2hex(random_bytes(12)),
            'name' => $functionName,
            'args' => '{}',
            'args_array' => [],
            'is_complete' => false,
            'chunk_count' => 0,
        ];

        $this->logger?->debug('GeminiStreamNewToolCall', [
            'candidate_index' => $candidateIndex,
            'tool_call_index' => $toolCallIndex,
            'function_name' => $functionName,
        ]);

        return $toolCallIndex;
    }

    /**
     * Merge arguments intelligently based on strategy.
     * Supports both complete replacement and incremental merging.
     *
     * @param int $candidateIndex Candidate index
     * @param int $toolCallIndex Tool call index
     * @param mixed $newArgs New arguments from current chunk
     * @return string JSON string of merged arguments
     */
    private function mergeArguments(int $candidateIndex, int $toolCallIndex, mixed $newArgs): string
    {
        $tracker = &$this->toolCallTracker[$candidateIndex][$toolCallIndex];
        ++$tracker['chunk_count'];

        // Convert new args to array
        $newArgsArray = is_object($newArgs) ? (array) $newArgs : (is_array($newArgs) ? $newArgs : []);

        // Empty args handling
        if (empty($newArgsArray)) {
            $this->logger?->debug('GeminiStreamEmptyArgs', [
                'candidate_index' => $candidateIndex,
                'tool_call_index' => $toolCallIndex,
                'chunk_count' => $tracker['chunk_count'],
            ]);
            return $tracker['args'];
        }

        $previousArgsArray = $tracker['args_array'];

        // Strategy: auto-detect or use configured strategy
        $strategy = $this->detectStrategy($previousArgsArray, $newArgsArray, $tracker['chunk_count']);

        $mergedArgsArray = match ($strategy) {
            'incremental' => $this->mergeIncremental($previousArgsArray, $newArgsArray, $candidateIndex, $toolCallIndex),
            default => $this->mergeComplete($previousArgsArray, $newArgsArray, $candidateIndex, $toolCallIndex),
        };

        // Update tracker
        $tracker['args_array'] = $mergedArgsArray;
        $tracker['args'] = json_encode($mergedArgsArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Check if args look complete (heuristic: no empty required fields)
        $tracker['is_complete'] = ! empty($mergedArgsArray);

        return $tracker['args'];
    }

    /**
     * Detect the best strategy for merging arguments.
     *
     * @param array $previousArgs Previous arguments
     * @param array $newArgs New arguments
     * @param int $chunkCount Number of chunks received
     * @return string Strategy: 'complete' or 'incremental'
     */
    private function detectStrategy(array $previousArgs, array $newArgs, int $chunkCount): string
    {
        // If strategy is explicitly set, use it
        if ($this->argsStrategy !== 'auto') {
            return $this->argsStrategy;
        }

        // First chunk: always use complete strategy
        if ($chunkCount === 1) {
            return 'complete';
        }

        // If new args have fewer keys than previous, likely complete replacement
        if (count($newArgs) < count($previousArgs)) {
            return 'complete';
        }

        // If new args have all the keys from previous args plus more, likely incremental
        $previousKeys = array_keys($previousArgs);
        $newKeys = array_keys($newArgs);
        $hasAllPreviousKeys = empty(array_diff($previousKeys, $newKeys));

        if ($hasAllPreviousKeys && count($newKeys) > count($previousKeys)) {
            $this->logger?->debug('GeminiStreamDetectedIncremental', [
                'previous_keys' => $previousKeys,
                'new_keys' => $newKeys,
            ]);
            return 'incremental';
        }

        // Default to complete (Gemini's observed behavior)
        return 'complete';
    }

    /**
     * Merge arguments using complete replacement strategy.
     * The new arguments completely replace the old ones.
     *
     * @param array $previousArgs Previous arguments
     * @param array $newArgs New arguments
     * @param int $candidateIndex Candidate index for logging
     * @param int $toolCallIndex Tool call index for logging
     * @return array Merged arguments
     */
    private function mergeComplete(array $previousArgs, array $newArgs, int $candidateIndex, int $toolCallIndex): array
    {
        // Check if args actually changed
        $argsChanged = $previousArgs !== $newArgs;

        if ($argsChanged) {
            $this->logger?->debug('GeminiStreamArgsReplaced', [
                'candidate_index' => $candidateIndex,
                'tool_call_index' => $toolCallIndex,
                'previous_args' => $previousArgs,
                'new_args' => $newArgs,
                'strategy' => 'complete',
            ]);
        }

        // Complete replacement: use new args entirely
        return $newArgs;
    }

    /**
     * Merge arguments using incremental strategy.
     * New arguments are merged into existing ones (deep merge).
     *
     * @param array $previousArgs Previous arguments
     * @param array $newArgs New arguments to merge in
     * @param int $candidateIndex Candidate index for logging
     * @param int $toolCallIndex Tool call index for logging
     * @return array Merged arguments
     */
    private function mergeIncremental(array $previousArgs, array $newArgs, int $candidateIndex, int $toolCallIndex): array
    {
        $merged = $this->deepMergeArrays($previousArgs, $newArgs);

        $this->logger?->debug('GeminiStreamArgsIncremented', [
            'candidate_index' => $candidateIndex,
            'tool_call_index' => $toolCallIndex,
            'previous_args' => $previousArgs,
            'new_args' => $newArgs,
            'merged_args' => $merged,
            'strategy' => 'incremental',
        ]);

        return $merged;
    }

    /**
     * Deep merge two arrays recursively.
     * New values override old values at the same path.
     *
     * @param array $array1 First array
     * @param array $array2 Second array (takes precedence)
     * @return array Merged array
     */
    private function deepMergeArrays(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Recursively merge arrays
                $merged[$key] = $this->deepMergeArrays($merged[$key], $value);
            } else {
                // Override with new value
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Cache thought signatures from all tool calls tracked during streaming.
     */
    private function cacheThoughtSignatures(): void
    {
        foreach ($this->toolCallTracker as $candidateIndex => $toolCalls) {
            foreach ($toolCalls as $toolCallIndex => $toolCall) {
                if (isset($toolCall['thought_signature'])) {
                    ThoughtSignatureCache::store($toolCall['id'], $toolCall['thought_signature']);
                }
            }
        }
    }
}

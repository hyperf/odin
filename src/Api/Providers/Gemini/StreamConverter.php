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
     * Structure: [candidateIndex => [toolCallIndex => ['id' => string, 'name' => string, 'args' => string]]]
     */
    private array $toolCallTracker = [];

    public function __construct(
        ResponseInterface $response,
        ?LoggerInterface $logger,
        string $model
    ) {
        $this->response = $response;
        $this->logger = $logger;
        $this->model = $model;
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
                // If there are tool calls, finish_reason should be 'tool_calls'
                if (! empty($delta['tool_calls'])) {
                    $choice['finish_reason'] = 'tool_calls';
                } else {
                    $choice['finish_reason'] = $this->convertFinishReason($candidate['finishReason']);
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
                $functionCall = $part['functionCall'];
                $functionName = $functionCall['name'] ?? '';
                $functionArgs = $functionCall['args'] ?? new stdClass();

                if (! isset($delta['tool_calls'])) {
                    $delta['tool_calls'] = [];
                }

                // Find existing tool call by name (same function call may appear in multiple chunks)
                // Use name to identify, as Gemini sends complete functionCall in each chunk
                $toolCallIndex = null;
                foreach ($this->toolCallTracker[$candidateIndex] as $idx => $tracked) {
                    if ($tracked['name'] === $functionName) {
                        $toolCallIndex = $idx;
                        break;
                    }
                }

                // Create new tool call if not found
                if ($toolCallIndex === null) {
                    $toolCallIndex = count($this->toolCallTracker[$candidateIndex]);
                    $this->toolCallTracker[$candidateIndex][$toolCallIndex] = [
                        'id' => 'call_' . bin2hex(random_bytes(12)),
                        'name' => $functionName,
                        'args' => '',
                    ];
                }

                // Convert args to JSON string
                // Gemini sends complete args in each chunk, so we always use the latest args
                $argsJson = json_encode($functionArgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                // Always update tracked args with the latest from current chunk
                // Gemini typically sends complete args, so we use the latest complete args
                if (! empty($argsJson)) {
                    $this->toolCallTracker[$candidateIndex][$toolCallIndex]['args'] = $argsJson;
                }

                // Use the tracked args (which should be the most complete)
                $finalArgs = $this->toolCallTracker[$candidateIndex][$toolCallIndex]['args'] ?: $argsJson;

                // Add tool call to delta
                $delta['tool_calls'][] = [
                    'index' => $toolCallIndex,
                    'id' => $this->toolCallTracker[$candidateIndex][$toolCallIndex]['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $functionName,
                        'arguments' => $finalArgs ?: '{}',
                    ],
                ];
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
        $promptTokens = $usageMetadata['promptTokenCount'] ?? 0;
        $completionTokens = $usageMetadata['candidatesTokenCount'] ?? 0;
        $totalTokens = $usageMetadata['totalTokenCount'] ?? ($promptTokens + $completionTokens);

        $usage = [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
        ];

        // Add cached tokens if present
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
    private function convertFinishReason(string $finishReason): string
    {
        return match ($finishReason) {
            'MAX_TOKENS' => 'length',
            'SAFETY', 'RECITATION' => 'content_filter',
            default => 'stop',
        };
    }
}

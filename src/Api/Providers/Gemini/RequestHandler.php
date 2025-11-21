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

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Utils\ImageDownloader;
use stdClass;

/**
 * Request Handler for converting OpenAI format to Gemini native format.
 */
class RequestHandler
{
    /**
     * Convert ChatCompletionRequest to Gemini native format.
     */
    public static function convertRequest(ChatCompletionRequest $request): array
    {
        $geminiRequest = [];

        // Convert messages to contents and extract system instructions
        $result = self::convertMessages($request->getMessages());

        $geminiRequest['contents'] = $result['contents'];

        // Add system instruction if present
        if (! empty($result['system_instruction'])) {
            $geminiRequest['system_instruction'] = $result['system_instruction'];
        }

        // Build generation config (includes thinking config)
        $generationConfig = self::buildGenerationConfig($request);
        if (! empty($generationConfig)) {
            $geminiRequest['generationConfig'] = $generationConfig;
        }

        // Convert tools if present
        $tools = $request->getTools();
        if (! empty($tools)) {
            $convertedTools = self::convertTools($tools);
            if (! empty($convertedTools)) {
                $geminiRequest['tools'] = $convertedTools;
            }
        }

        return $geminiRequest;
    }

    /**
     * Convert UserMessage to Gemini format.
     * Made public for use in GeminiCacheManager.
     */
    public static function convertUserMessage(UserMessage $message): array
    {
        $parts = [];

        // Handle multimodal content (text + images)
        if ($message->getContents() !== null) {
            foreach ($message->getContents() as $content) {
                // Use object methods directly
                $type = $content->getType();

                if ($type === UserMessageContent::TEXT) {
                    $parts[] = ['text' => $content->getText()];
                } elseif ($type === UserMessageContent::IMAGE_URL) {
                    // Auto-detect URL format and convert accordingly:
                    // - data:image/...;base64,... -> inline_data
                    // - https://generativelanguage.googleapis.com/v1beta/files/... -> file_data
                    // - other HTTP URLs -> text placeholder
                    $imageUrl = $content->getImageUrl();
                    $parts[] = self::convertImageUrl($imageUrl);
                }
            }
        } else {
            // Simple text content
            $parts[] = ['text' => $message->getContent()];
        }

        return [
            'role' => 'user',
            'parts' => $parts,
        ];
    }

    /**
     * Convert tools from OpenAI format to Gemini FunctionDeclaration format.
     * Made public for use in GeminiCacheManager.
     */
    public static function convertTools(array $tools): array
    {
        $functionDeclarations = [];

        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $tool = $tool->toToolDefinition();
            }

            if (! $tool instanceof ToolDefinition) {
                continue;
            }

            $declaration = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
            ];

            // Add parameters if present
            $parameters = $tool->getParameters();
            if ($parameters !== null) {
                $declaration['parameters'] = $parameters->toArray();
            } else {
                // Provide empty parameters schema
                $declaration['parameters'] = [
                    'type' => 'object',
                    'properties' => new stdClass(),
                ];
            }

            $functionDeclarations[] = $declaration;
        }

        if (empty($functionDeclarations)) {
            return [];
        }

        // Gemini expects tools array with functionDeclarations
        return [
            [
                'functionDeclarations' => $functionDeclarations,
            ],
        ];
    }

    /**
     * Convert messages array from OpenAI format to Gemini contents format.
     * Made public for use in cache strategies (GlobalCacheStrategy, UserCacheStrategy).
     *
     * @return array{contents: array, system_instruction: null|array}
     */
    public static function convertMessages(array $messages): array
    {
        $contents = [];
        $systemInstructions = [];

        // Track tool_call_id to function name mapping
        // This is needed because OpenAI ToolMessage only has tool_call_id,
        // but Gemini functionResponse requires the function name
        $toolCallIdToName = [];

        foreach ($messages as $message) {
            if (! $message instanceof MessageInterface) {
                continue;
            }

            // Handle system messages separately - extract to system_instruction
            if ($message instanceof SystemMessage) {
                if ($message->getContent() === '') {
                    continue;
                }
                $systemInstructions[] = $message->getContent();
                continue;
            }

            // Track tool calls from assistant messages
            if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                foreach ($message->getToolCalls() as $toolCall) {
                    $toolCallIdToName[$toolCall->getId()] = $toolCall->getName();
                }
            }

            $content = match (true) {
                $message instanceof UserMessage => self::convertUserMessage($message),
                $message instanceof AssistantMessage => self::convertAssistantMessage($message),
                $message instanceof ToolMessage => self::convertToolMessage($message, $toolCallIdToName),
                default => null,
            };

            if ($content !== null) {
                $contents[] = $content;
            }
        }

        // Build system instruction in Gemini format
        $systemInstruction = null;
        if (! empty($systemInstructions)) {
            $systemText = implode("\n\n", $systemInstructions);
            $systemInstruction = [
                'parts' => [
                    ['text' => $systemText],
                ],
            ];
        }

        return [
            'contents' => $contents,
            'system_instruction' => $systemInstruction,
        ];
    }

    /**
     * Convert AssistantMessage to Gemini format.
     */
    private static function convertAssistantMessage(AssistantMessage $message): array
    {
        $parts = [];

        // Add text content if present
        if ($message->getContent()) {
            $parts[] = ['text' => $message->getContent()];
        }

        // Add tool calls as functionCall parts
        if ($message->hasToolCalls()) {
            foreach ($message->getToolCalls() as $toolCall) {
                $arguments = $toolCall->getArguments();

                // Build functionCall part
                $functionCall = [
                    'name' => $toolCall->getName(),
                ];

                // Only add args if there are actual arguments
                // Gemini API doesn't accept empty args field, so omit it when empty
                if (! empty($arguments) && ! array_is_list($arguments)) {
                    // Convert associative array to object for JSON encoding
                    $functionCall['args'] = (object) $arguments;
                }

                $part = [
                    'functionCall' => $functionCall,
                ];

                // Get thought_signature if available (only for Gemini 3 and 2.5 models with thinking mode)
                // Priority: ToolCall object -> Cache
                $thoughtSignature = $toolCall->getThoughtSignature();
                if (! $thoughtSignature) {
                    $thoughtSignature = ThoughtSignatureCache::get($toolCall->getId());
                    $toolCall->setThoughtSignature($thoughtSignature);
                }

                if ($thoughtSignature) {
                    $part['thoughtSignature'] = $thoughtSignature;
                }

                $parts[] = $part;
            }
        }

        return [
            'role' => 'model', // Gemini uses 'model' instead of 'assistant'
            'parts' => $parts,
        ];
    }

    /**
     * Convert ToolMessage to Gemini format.
     *
     * @param ToolMessage $message The tool message to convert
     * @param array $toolCallIdToName Mapping of tool_call_id to function name
     */
    private static function convertToolMessage(ToolMessage $message, array $toolCallIdToName = []): array
    {
        $content = $message->getContent();
        $result = json_decode($content, true);

        // If not valid JSON, wrap it
        if ($result === null) {
            $result = ['result' => $content];
        }

        // Get tool name - Gemini requires it to be non-empty
        // Priority: 1) message.name 2) lookup by tool_call_id 3) fallback
        $toolName = $message->getName();

        if (empty($toolName)) {
            // Try to find name by tool_call_id from previous assistant message
            $toolCallId = $message->getToolCallId();
            $toolName = $toolCallIdToName[$toolCallId] ?? null;

            if (empty($toolName)) {
                // Use tool_call_id as last resort fallback
                $toolName = $toolCallId ?: 'function_response';
            }
        }

        return [
            'role' => 'user', // Tool responses come back as user role in Gemini
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $toolName,
                        'response' => $result,
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert image URL to Gemini format.
     * Supports both inline_data (base64) and file_data (file URI) formats.
     * For remote URLs, downloads and converts to base64 format first.
     */
    private static function convertImageUrl(string $imageUrl): array
    {
        // If it's a remote URL, download and convert to base64 first
        if (ImageDownloader::isRemoteImageUrl($imageUrl)) {
            $imageUrl = ImageDownloader::downloadAndConvertToBase64($imageUrl);
        }

        // Check if it's a data URL (base64 encoded)
        if (str_starts_with($imageUrl, 'data:')) {
            // Extract mime type and base64 data
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $imageUrl, $matches)) {
                $mimeType = $matches[1];
                // Only process if it's an image MIME type
                if (self::isImageMimeType($mimeType)) {
                    return [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $matches[2],
                        ],
                    ];
                }
            }
            // If data URL but not an image, fall through to text
        }

        // For non-image URLs, return as text
        return [
            'text' => "[Image: {$imageUrl}]",
        ];
    }

    /**
     * Check if MIME type is a Gemini supported image type.
     * Gemini supports: image/png, image/jpeg, image/webp, image/heic, image/heif.
     */
    private static function isImageMimeType(string $mimeType): bool
    {
        $supportedMimeTypes = [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/heic',
            'image/heif',
        ];

        return in_array(strtolower($mimeType), $supportedMimeTypes, true);
    }

    /**
     * Build generation config from request parameters.
     */
    private static function buildGenerationConfig(ChatCompletionRequest $request): array
    {
        $config = [];

        // Temperature
        $temperature = $request->getTemperature();
        if ($temperature !== 0.5) { // Only add if not default
            $config['temperature'] = $temperature;
        }

        // Max tokens
        $maxTokens = $request->getMaxTokens();
        if ($maxTokens > 0) {
            $config['maxOutputTokens'] = $maxTokens;
        }

        // Stop sequences
        $stop = $request->getStop();
        if (! empty($stop)) {
            $config['stopSequences'] = $stop;
        }

        // According to API docs, thinkingConfig should be inside generationConfig
        $thinking = $request->getThinking();
        if (! empty($thinking)) {
            $thinkingConfig = self::convertThinkingConfig($thinking);
            if (! empty($thinkingConfig)) {
                $config['thinkingConfig'] = $thinkingConfig;
            }
        }

        return $config;
    }

    /**
     * Convert thinking config to Gemini format.
     */
    private static function convertThinkingConfig(array $thinking): array
    {
        $config = [];

        // Map thinking budget if present
        if (isset($thinking['thinking_budget'])) {
            $config['thinkingBudget'] = $thinking['thinking_budget'];
        }

        return $config;
    }
}

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
use stdClass;

/**
 * Request Handler for converting OpenAI format to Gemini native format.
 */
class RequestHandler
{
    /**
     * Convert ChatCompletionRequest to Gemini native format.
     */
    public static function convertRequest(ChatCompletionRequest $request, string $model): array
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
     * Convert messages array from OpenAI format to Gemini contents format.
     *
     * @return array{contents: array, system_instruction: null|array}
     */
    private static function convertMessages(array $messages): array
    {
        $contents = [];
        $systemInstructions = [];

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

            $content = match (true) {
                $message instanceof UserMessage => self::convertUserMessage($message),
                $message instanceof AssistantMessage => self::convertAssistantMessage($message),
                $message instanceof ToolMessage => self::convertToolMessage($message),
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
     * Convert UserMessage to Gemini format.
     */
    private static function convertUserMessage(UserMessage $message): array
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

                // Decode JSON string to array if needed
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true) ?? [];
                }

                // Build functionCall part
                $functionCall = [
                    'name' => $toolCall->getName(),
                ];

                // Only add args if there are actual arguments
                // Gemini API doesn't accept empty args field, so omit it when empty
                if (!empty($arguments) && !(is_array($arguments) && array_is_list($arguments))) {
                    // Convert associative array to object for JSON encoding
                    $functionCall['args'] = (object) $arguments;
                }

                $parts[] = [
                    'functionCall' => $functionCall,
                ];
            }
        }

        return [
            'role' => 'model', // Gemini uses 'model' instead of 'assistant'
            'parts' => $parts,
        ];
    }

    /**
     * Convert ToolMessage to Gemini format.
     */
    private static function convertToolMessage(ToolMessage $message): array
    {
        $content = $message->getContent();
        $result = json_decode($content, true);

        // If not valid JSON, wrap it
        if ($result === null) {
            $result = ['result' => $content];
        }

        return [
            'role' => 'user', // Tool responses come back as user role in Gemini
            'parts' => [
                [
                    'functionResponse' => [
                        'name' => $message->getName(),
                        'response' => $result,
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert image URL to Gemini format.
     * Supports both inline_data (base64) and file_data (file URI) formats.
     */
    private static function convertImageUrl(string $imageUrl): array
    {
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

        // Check if it's an image URL by extension
        if (self::isImageUrl($imageUrl)) {
            // For image URLs, use file_data format
            $mimeType = self::inferMimeTypeFromUrl($imageUrl);

            return [
                'file_data' => [
                    'file_uri' => $imageUrl,
                    'mime_type' => $mimeType,
                ],
            ];
        }

        // For non-image URLs, return as text
        return [
            'text' => "[Image: {$imageUrl}]",
        ];
    }

    /**
     * Check if URL is an image URL based on file extension.
     * Only supports Gemini supported formats: PNG, JPEG, WEBP, HEIC, HEIF.
     */
    private static function isImageUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Gemini supported image extensions only
        return in_array($extension, [
            'jpg', 'jpeg', // JPEG
            'png',         // PNG
            'webp',       // WEBP
            'heic',       // HEIC
            'heif',       // HEIF
        ], true);
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
     * Infer MIME type from URL file extension.
     * Only returns Gemini supported MIME types: image/png, image/jpeg, image/webp, image/heic, image/heif.
     */
    private static function inferMimeTypeFromUrl(string $url): string
    {
        // Extract file extension
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null) {
            return 'image/jpeg'; // Default fallback
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Gemini supported image MIME types only
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            default => 'image/jpeg', // Default fallback
        };
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

        // Add thinking config if present (Gemini 2.5+)
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
     * Convert tools from OpenAI format to Gemini FunctionDeclaration format.
     */
    private static function convertTools(array $tools): array
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

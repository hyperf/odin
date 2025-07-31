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

use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use stdClass;

class ConverseConverter implements ConverterInterface
{
    public function convertSystemMessage(SystemMessage $message): array|string
    {
        $data = [
            [
                'text' => $message->getContent(),
            ],
        ];
        if ($message->getCachePoint()) {
            $data[] = [
                'cachePoint' => [
                    'type' => 'default',
                ],
            ];
        }
        return $data;
    }

    public function convertToolMessage(ToolMessage $message): array
    {
        $contentBlocks = [];
        $hasCachePoint = false;

        // Determine which tool messages to process
        if ($message instanceof MergedToolMessage) {
            // Handle merged tool message (multiple tool results)
            $toolMessages = $message->getToolMessages();
        } else {
            // Handle single tool message
            $toolMessages = [$message];
        }

        // Process each tool message
        foreach ($toolMessages as $toolMessage) {
            // Check if this tool message has a cache point
            if ($toolMessage->getCachePoint()) {
                $hasCachePoint = true;
            }

            $content = $toolMessage->getContent();
            $result = json_decode($content, true);

            if (! $result) {
                $result = [
                    'result' => $content,
                ];
            } else {
                // Check if the original JSON was an empty array [] vs empty object {}
                if (trim($content) === '[]') {
                    // Original was empty array [], wrap it in result
                    $result = [
                        'result' => $result,
                    ];
                } elseif (is_array($result) && array_is_list($result)) {
                    // It's an indexed array (not associative), wrap it in result
                    $result = [
                        'list' => $result,
                    ];
                } elseif ($result === []) {
                    // It was empty object {}, convert to empty object
                    $result = new stdClass();
                }
            }

            $contentBlocks[] = [
                'toolResult' => [
                    'toolUseId' => $toolMessage->getToolCallId(),
                    'content' => [
                        [
                            'json' => $result,
                        ],
                    ],
                ],
            ];
        }

        // Add cache point if any of the original tool messages has one
        if ($hasCachePoint) {
            $contentBlocks[] = [
                'cachePoint' => [
                    'type' => 'default',
                ],
            ];
        }

        return [
            'role' => Role::User->value,
            'content' => $contentBlocks,
        ];
    }

    public function convertAssistantMessage(AssistantMessage $message): array
    {
        $contentBlocks = [];
        // 检查是否包含工具调用
        if (empty($message->getToolCalls())) {
            $contentBlocks[] = [
                'text' => $message->getContent(),
            ];
        } else {
            // 处理包含工具调用的消息
            // 1. 添加文本内容(如果有)
            if ($message->getContent()) {
                $contentBlocks[] = [
                    'text' => $message->getContent(),
                ];
            }

            // 2. 添加工具调用内容
            foreach ($message->getToolCalls() as $toolCall) {
                $arguments = $toolCall->getArguments();
                if (empty($arguments)) {
                    $arguments = new stdClass();
                }
                $contentBlocks[] = [
                    'toolUse' => [
                        'toolUseId' => $toolCall->getId(),
                        'name' => $toolCall->getName(),
                        'input' => $arguments,
                    ],
                ];
            }
        }
        if ($message->getCachePoint()) {
            $contentBlocks[] = [
                'cachePoint' => [
                    'type' => 'default',
                ],
            ];
        }

        return [
            'role' => Role::Assistant->value,
            'content' => $contentBlocks,
        ];
    }

    public function convertUserMessage(UserMessage $message): array
    {
        $contentBlocks = [];

        // 处理UserMessage的多模态内容(例如图像)
        if ($message->getContents() !== null) {
            $contentBlocks = $this->processMultiModalContents($message);
        } else {
            $contentBlocks[] = [
                'text' => $message->getContent(),
            ];
        }
        if ($message->getCachePoint()) {
            $contentBlocks[] = [
                'cachePoint' => [
                    'type' => 'default',
                ],
            ];
        }

        return [
            'role' => Role::User->value,
            'content' => $contentBlocks,
        ];
    }

    public function convertTools(array $tools, bool $cache = false): array
    {
        // 将OpenAI格式的工具定义转换为Anthropic API格式
        $convertedTools = [];

        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $tool = $tool->toToolDefinition();
            }
            if (! $tool instanceof ToolDefinition) {
                continue;
            }

            $convertedTool = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
            ];
            if ($tool->getParameters() !== null) {
                $convertedTool['inputSchema'] = [
                    'json' => $tool->getParameters()->toArray(),
                ];
            } else {
                // 没有参数时提供默认的空对象
                $convertedTool['inputSchema'] = [
                    'json' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ];
            }
            $convertedTools[] = [
                'toolSpec' => $convertedTool,
            ];
        }
        if ($cache) {
            $convertedTools[] = [
                'cachePoint' => [
                    'type' => 'default',
                ],
            ];
        }

        return $convertedTools;
    }

    /**
     * 处理多模态内容(文本和图像等).
     *
     * @param UserMessage $message 包含多模态内容的用户消息
     * @return array 内容块数组
     */
    private function processMultiModalContents(UserMessage $message): array
    {
        $contentBlocks = [];

        foreach ($message->getContents() as $content) {
            // 处理文本内容
            if ($content->getType() === 'text' && ! empty($content->getText())) {
                $contentBlocks[] = [
                    'text' => $content->getText(),
                ];
            }
            // 处理图像内容
            elseif ($content->getType() === 'image_url' && ! empty($content->getImageUrl())) {
                $contentBlocks[] = $this->processImageUrl($content->getImageUrl());
            }
        }

        return $contentBlocks;
    }

    /**
     * 处理图像URL并转换为适合AWS Bedrock Claude格式的图像数据.
     *
     * @param string $imageUrl 图像URL（必须是 data:image 格式的 base64 编码数据）
     * @return array Claude 格式的图像数据
     */
    private function processImageUrl(string $imageUrl): array
    {
        // 检查是否为base64编码的Data URL
        if (str_starts_with($imageUrl, 'data:image/') && str_contains($imageUrl, ';base64,')) {
            // 提取MIME类型和base64数据
            [$metaData, $base64Data] = explode(',', $imageUrl, 2);
            preg_match('/data:(image\/[^;]+)/', $metaData, $matches);
            $mimeType = $matches[1] ?? 'image/jpeg';
            $format = explode('/', $mimeType)[1] ?? 'jpeg';

            return [
                'image' => [
                    'format' => $format,
                    'source' => [
                        'bytes' => base64_decode($base64Data),
                    ],
                ],
            ];
        }

        // 对于非 base64 编码的 URL，抛出异常
        throw new LLMInvalidRequestException('图像URL必须是 base64 编码格式 (data:image/xxx;base64,...)');
    }
}

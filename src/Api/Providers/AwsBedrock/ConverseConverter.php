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

class ConverseConverter implements ConverterInterface
{
    public function convertSystemMessage(SystemMessage $message): array|string
    {
        return [
            [
                'text' => $message->getContent(),
            ],
        ];
    }

    public function convertToolMessage(ToolMessage $message): array
    {
        $result = json_decode($message->getContent(), true);
        if (! $result) {
            $result = [$message->getContent()];
        }
        return [
            'role' => Role::User->value,
            'content' => [
                [
                    'toolResult' => [
                        'toolUseId' => $message->getToolCallId(),
                        'content' => [
                            [
                                'json' => $result,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function convertAssistantMessage(AssistantMessage $message): array
    {
        // 检查是否包含工具调用
        if (empty($message->getToolCalls())) {
            return [
                'role' => Role::Assistant->value,
                'content' => $message->getContent(),
            ];
        }

        // 处理包含工具调用的消息
        $contentBlocks = [];

        // 1. 添加文本内容(如果有)
        if ($message->getContent()) {
            $contentBlocks[] = [
                'text' => $message->getContent(),
            ];
        }

        // 2. 添加工具调用内容
        foreach ($message->getToolCalls() as $toolCall) {
            $contentBlocks[] = [
                'toolUse' => [
                    'toolUseId' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                    'input' => $toolCall->getArguments(),
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
        $convertedMessage = $message->toArray();
        if (isset($convertedMessage['content']) && is_string($convertedMessage['content'])) {
            $convertedMessage['content'] = [
                [
                    'text' => $convertedMessage['content'],
                ],
            ];
        }

        // 处理UserMessage的多模态内容(例如图像)
        if ($message->getContents() !== null) {
            $contentBlocks = $this->processMultiModalContents($message);

            if (! empty($contentBlocks)) {
                if ($message->getCachePoint()) {
                    $contentBlocks[] = [
                        'cachePoint' => [
                            'type' => 'default',
                        ],
                    ];
                }
                $convertedMessage['content'] = $contentBlocks;
            }
        } else {
            if ($message->getCachePoint()) {
                $convertedMessage['content'] = [
                    [
                        'type' => 'text',
                        'text' => $message->getContent(),
                        'cachePoint' => [
                            'type' => 'default',
                        ],
                    ],
                ];
            }
        }

        return $convertedMessage;
    }

    public function convertTools(array $tools): array
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

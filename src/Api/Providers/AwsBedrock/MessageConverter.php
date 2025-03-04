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

use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use RuntimeException;

/**
 * 消息转换辅助类.
 *
 * 提供将各种格式消息转换为 AWS Bedrock Claude API 格式的静态方法
 */
class MessageConverter
{
    /**
     * 转换消息为Anthropic Claude API格式，并分离出system消息.
     *
     * @param array $messages 原始消息数组
     * @return array 包含system和messages的数组
     */
    public static function convertMessages(array $messages): array
    {
        $converted = [];
        $systemMessage = '';

        foreach ($messages as $message) {
            // 跳过非MessageInterface实例
            if (! $message instanceof MessageInterface) {
                continue;
            }

            // 根据消息类型分别处理
            match (true) {
                // 1. 处理系统消息 - 单独提取
                $message instanceof SystemMessage => $systemMessage = $message->getContent(),

                // 2. 处理工具结果消息 - 转换为tool_result格式
                $message instanceof ToolMessage => $converted[] = self::convertToolMessage($message),

                // 3. 处理助手消息 - 可能包含工具调用
                $message instanceof AssistantMessage => $converted[] = self::convertAssistantMessage($message),

                // 4. 处理其他类型消息(主要是用户消息)
                default => $converted[] = self::convertUserMessage($message)
            };
        }

        return [
            'system' => $systemMessage,
            'messages' => $converted,
        ];
    }

    /**
     * @param array<ToolDefinition> $tools
     */
    public static function convertTools(array $tools): array
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
                $convertedTool['input_schema'] = $tool->getParameters()->toArray();
            } else {
                // 没有参数时提供默认的空对象
                $convertedTool['input_schema'] = [
                    'type' => 'object',
                    'properties' => [],
                ];
            }
            $convertedTools[] = $convertedTool;
        }

        return $convertedTools;
    }

    /**
     * 处理图像URL并转换为适合AWS Bedrock Claude格式的图像数据.
     *
     * @param string $imageUrl 图像URL（必须是 data:image 格式的 base64 编码数据）
     * @return array Claude 格式的图像数据
     * @throws RuntimeException 当 URL 不是 base64 编码格式时抛出
     */
    private static function processImageUrl(string $imageUrl): array
    {
        // 检查是否为base64编码的Data URL
        if (str_starts_with($imageUrl, 'data:image/') && str_contains($imageUrl, ';base64,')) {
            // 提取MIME类型和base64数据
            [$metaData, $base64Data] = explode(',', $imageUrl, 2);
            preg_match('/data:(image\/[^;]+)/', $metaData, $matches);
            $mimeType = $matches[1] ?? 'image/jpeg';

            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mimeType,
                    'data' => $base64Data,
                ],
            ];
        }

        // 对于非 base64 编码的 URL，抛出异常
        throw new LLMException\Api\LLMInvalidRequestException('图像URL必须是 base64 编码格式 (data:image/xxx;base64,...)');
    }

    /**
     * 转换ToolMessage为Claude API格式.
     *
     * @param ToolMessage $message 工具消息
     * @return array 转换后的消息数组
     */
    private static function convertToolMessage(ToolMessage $message): array
    {
        return [
            'role' => Role::User->value,
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $message->getToolCallId(),
                    'content' => $message->getContent(),
                ],
            ],
        ];
    }

    /**
     * 转换AssistantMessage为Claude API格式.
     *
     * @param AssistantMessage $message 助手消息
     * @return array 转换后的消息数组
     */
    private static function convertAssistantMessage(AssistantMessage $message): array
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
                'type' => 'text',
                'text' => $message->getContent(),
            ];
        }

        // 2. 添加工具调用内容
        foreach ($message->getToolCalls() as $toolCall) {
            $contentBlocks[] = [
                'type' => 'tool_use',
                'id' => $toolCall->getId(),
                'name' => $toolCall->getName(),
                'input' => $toolCall->getArguments(),
            ];
        }

        return [
            'role' => Role::Assistant->value,
            'content' => $contentBlocks,
        ];
    }

    /**
     * 转换UserMessage或其他消息为Claude API格式.
     *
     * @param MessageInterface $message 用户消息或其他类型消息
     * @return array 转换后的消息数组
     */
    private static function convertUserMessage(MessageInterface $message): array
    {
        $convertedMessage = $message->toArray();

        // 处理UserMessage的多模态内容(例如图像)
        if ($message instanceof UserMessage && $message->getContents() !== null) {
            $contentBlocks = self::processMultiModalContents($message);

            if (! empty($contentBlocks)) {
                $convertedMessage['content'] = $contentBlocks;
            }
        }

        return $convertedMessage;
    }

    /**
     * 处理多模态内容(文本和图像等).
     *
     * @param UserMessage $message 包含多模态内容的用户消息
     * @return array 内容块数组
     */
    private static function processMultiModalContents(UserMessage $message): array
    {
        $contentBlocks = [];

        foreach ($message->getContents() as $content) {
            // 处理文本内容
            if ($content->getType() === 'text' && ! empty($content->getText())) {
                $contentBlocks[] = [
                    'type' => 'text',
                    'text' => $content->getText(),
                ];
            }
            // 处理图像内容
            elseif ($content->getType() === 'image_url' && ! empty($content->getImageUrl())) {
                $contentBlocks[] = self::processImageUrl($content->getImageUrl());
            }
        }

        return $contentBlocks;
    }
}

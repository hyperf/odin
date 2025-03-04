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

use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Api\Response\Usage;
use Psr\Http\Message\ResponseInterface;

/**
 * 响应处理辅助类.
 *
 * 提供将 AWS Bedrock 响应转换为标准格式的静态方法
 */
class ResponseHandler
{
    /**
     * 将AWS Bedrock响应转换为符合PSR-7标准的Response对象.
     */
    public static function convertToPsrResponse(array $responseBody, string $model): ResponseInterface
    {
        $content = '';
        $functionCalls = [];

        if (isset($responseBody['content']) && is_array($responseBody['content'])) {
            foreach ($responseBody['content'] as $item) {
                if (isset($item['type']) && $item['type'] === 'text') {
                    $content .= $item['text'];
                } elseif (isset($item['type']) && $item['type'] === 'tool_use') {
                    // 处理工具调用响应 - Anthropic格式
                    $functionCalls[] = [
                        'id' => $item['id'] ?? uniqid('fc-'),
                        'type' => 'function',
                        'function' => [
                            'name' => $item['name'],
                            'arguments' => isset($item['input']) ? json_encode($item['input']) : '{}',
                        ],
                    ];
                }
            }
        }

        // 构建OpenAI格式的响应
        $messageContent = $content;
        $message = [
            'role' => 'assistant',
            'content' => $messageContent,
        ];

        // 如果有工具调用，添加到消息中
        if (! empty($functionCalls)) {
            $message['tool_calls'] = $functionCalls;
        }

        $choiceArray = [
            'message' => $message,
            'index' => 0,
            'finish_reason' => ! empty($functionCalls) ? 'tool_calls' : ($responseBody['stop_reason'] ?? 'stop'),
        ];

        // 创建使用量对象（如果有）
        if (isset($responseBody['usage'])) {
            $usage = Usage::fromArray([
                'prompt_tokens' => $responseBody['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $responseBody['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($responseBody['usage']['input_tokens'] ?? 0) + ($responseBody['usage']['output_tokens'] ?? 0),
            ]);
        } else {
            $usage = Usage::fromArray([
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ]);
        }

        // 构造一个模拟的HTTP响应，包含我们需要的数据
        $jsonResponse = json_encode([
            'id' => $responseBody['id'] ?? uniqid('bedrock-'),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $responseBody['model'] ?? $model,
            'choices' => [$choiceArray],
            'usage' => [
                'prompt_tokens' => $usage->getPromptTokens(),
                'completion_tokens' => $usage->getCompletionTokens(),
                'total_tokens' => $usage->getTotalTokens(),
            ],
        ]);

        // 创建一个 PSR-7 响应对象并返回
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            $jsonResponse
        );
    }
}

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
        $reasoningContent = '';
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
                } elseif (isset($item['type']) && $item['type'] === 'thinking') {
                    $reasoningContent .= $item['thinking'] ?? '';
                }
            }
        }

        // 构建OpenAI格式的响应
        $messageContent = $content;
        $message = [
            'role' => 'assistant',
            'content' => $messageContent,
        ];
        if ($reasoningContent !== '') {
            $message['reasoning_content'] = $reasoningContent;
        }

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
                'total_tokens' => $responseBody['usage']['total_tokens'] ?? 0,
                'prompt_tokens_details' => $responseBody['usage']['prompt_tokens_details'] ?? [],
                'completion_tokens_details' => $responseBody['usage']['completion_tokens_details'] ?? [],
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
            'usage' => $usage->toArray(),
        ]);

        // 创建一个 PSR-7 响应对象并返回
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            $jsonResponse
        );
    }

    public static function convertConverseToPsrResponse(array $output, array $usage, string $model): ResponseInterface
    {
        $responseBody = [
            'usage' => [
                'input_tokens' => $usage['inputTokens'] ?? 0,
                'output_tokens' => $usage['outputTokens'] ?? 0,
                'total_tokens' => $usage['totalTokens'] ?? 0,
                'prompt_tokens_details' => [
                    'cache_write_input_tokens' => $usage['cacheWriteInputTokens'] ?? 0,
                    'cache_read_input_tokens' => $usage['cacheReadInputTokens'] ?? 0,
                    // 兼容旧参数
                    'audio_tokens' => 0,
                    'cached_tokens' => $usage['cacheWriteInputTokens'] ?? 0,
                ],
                'completion_tokens_details' => [
                    'reasoning_tokens' => 0,
                ],
            ],
        ];
        $content = [];
        if (isset($output['message']['content']) && is_array($output['message']['content'])) {
            foreach ($output['message']['content'] as $item) {
                if (isset($item['text'])) {
                    $content[] = [
                        'type' => 'text',
                        'text' => $item['text'],
                    ];
                }
                if (isset($item['toolUse'])) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $item['toolUse']['toolUseId'] ?? uniqid('fc-'),
                        'name' => $item['toolUse']['name'],
                        'input' => $item['toolUse']['input'] ?? [],
                    ];
                }
                if (isset($item['thinking'])) {
                    $content[] = [
                        'type' => 'thinking',
                        'thinking' => $item['thinking'],
                    ];
                }
            }
        }
        $responseBody['content'] = $content;

        return self::convertToPsrResponse($responseBody, $model);
    }
}

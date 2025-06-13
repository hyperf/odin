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

namespace Hyperf\Odin\Contract\Model;

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\TextCompletionResponse;
use Hyperf\Odin\Contract\Mcp\McpServerManagerInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;

interface ModelInterface
{
    public function registerMcpServerManager(?McpServerManagerInterface $mcpServerManager): void;

    public function getMcpServerManager(): ?McpServerManagerInterface;

    /**
     * @param array<MessageInterface> $messages
     */
    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionResponse;

    /**
     * 使用请求对象进行聊天对话.
     *
     * @param ChatCompletionRequest $request 聊天完成请求对象
     * @return ChatCompletionResponse 聊天完成响应
     */
    public function chatWithRequest(ChatCompletionRequest $request): ChatCompletionResponse;

    /**
     * @param array<MessageInterface> $messages
     */
    public function chatStream(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionStreamResponse;

    /**
     * 使用请求对象进行流式聊天对话.
     *
     * @param ChatCompletionRequest $request 聊天完成请求对象
     * @return ChatCompletionStreamResponse 聊天完成流式响应
     */
    public function chatStreamWithRequest(ChatCompletionRequest $request): ChatCompletionStreamResponse;

    public function completions(
        string $prompt,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): TextCompletionResponse;
}

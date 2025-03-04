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

namespace Hyperf\Odin\Contract\Api;

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Request\CompletionRequest;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Api\Response\TextCompletionResponse;

interface ClientInterface
{
    /**
     * 后续有特殊接口的传参，实现\Hyperf\Odin\Api\Request\ChatCompletionRequest::createOptions.
     */
    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse;

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse;

    /**
     * 创建文本嵌入向量，用于文本相似度搜索或其他机器学习任务.
     * 参照 OpenAI API 实现：https://platform.openai.com/docs/api-reference/embeddings.
     */
    public function embeddings(EmbeddingRequest $embeddingRequest): EmbeddingResponse;

    /**
     * 使用 completions 接口生成文本补全.
     * 这是一个历史接口，主要用于向后兼容.
     * 参照 OpenAI API 实现：https://platform.openai.com/docs/api-reference/completions.
     */
    public function completions(CompletionRequest $completionRequest): TextCompletionResponse;
}

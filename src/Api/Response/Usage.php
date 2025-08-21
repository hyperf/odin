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

namespace Hyperf\Odin\Api\Response;

class Usage
{
    /**
     * @param int $promptTokens 提示词的令牌数量
     * @param int $completionTokens 完成内容的令牌数量
     * @param int $totalTokens 使用的总令牌数量
     * @param array $completionTokensDetails 完成令牌的详细信息
     * @param array $promptTokensDetails 提示令牌的详细信息，可能包含：
     *                                   - cache_write_input_tokens: 写入缓存的令牌数量
     *                                   - cache_read_input_tokens: 从缓存读取的令牌数量（命中的缓存）
     *                                   - cached_tokens: 从缓存读取的令牌数量（命中的缓存）
     */
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public array $completionTokensDetails = [],
        public array $promptTokensDetails = []
    ) {}

    public static function fromArray(array $usage): self
    {
        return new self(
            $usage['prompt_tokens'] ?? 0,
            $usage['completion_tokens'] ?? 0,
            $usage['total_tokens'] ?? 0,
            $usage['completion_tokens_details'] ?? [],
            $usage['prompt_tokens_details'] ?? []
        );
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function getCompletionTokensDetails(): array
    {
        return $this->completionTokensDetails;
    }

    public function getPromptTokensDetails(): array
    {
        return $this->promptTokensDetails;
    }

    /**
     * 获取写入缓存的令牌数量
     */
    public function getCacheWriteInputTokens(): int
    {
        return (int) ($this->promptTokensDetails['cache_write_input_tokens'] ?? 0);
    }

    /**
     * 获取从缓存读取的令牌数量（命中的缓存）
     */
    public function getCacheReadInputTokens(): int
    {
        return (int) ($this->promptTokensDetails['cache_read_input_tokens'] ?? 0);
    }

    /**
     * 获取缓存令牌数量（命中的缓存）
     */
    public function getCachedTokens(): int
    {
        return (int) ($this->promptTokensDetails['cached_tokens'] ?? 0);
    }

    /**
     * 检查是否有缓存命中
     */
    public function hasCacheHit(): bool
    {
        return $this->getCacheReadInputTokens() > 0 || $this->getCachedTokens() > 0;
    }

    public function toArray(): array
    {
        $data = [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
        if (! empty($this->promptTokensDetails)) {
            $data['prompt_tokens_details'] = $this->promptTokensDetails;
        }
        if (! empty($this->completionTokensDetails)) {
            $data['completion_tokens_details'] = $this->completionTokensDetails;
        }
        return $data;
    }
}

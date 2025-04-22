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

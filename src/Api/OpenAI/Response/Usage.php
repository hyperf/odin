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

namespace Hyperf\Odin\Api\OpenAI\Response;

class Usage
{
    public function __construct(public int $promptTokens, public int $completionTokens, public int $totalTokens) {}

    public static function fromArray(array $usage): static
    {
        return new static($usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0);
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
}

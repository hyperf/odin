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

namespace Hyperf\Odin\Memory;

use Stringable;

abstract class AbstractMemory
{
    protected array $conversations = [];

    abstract public function addHumanMessage(
        string|Stringable $input,
        string|Stringable|null $conversationId,
        string $prefix = 'User: '
    ): static;

    abstract public function addAIMessage(
        string|Stringable $output,
        string|Stringable|null $conversationId,
        string $prefix = 'AI: '
    ): static;

    abstract public function addMessage(string|Stringable $message, string|Stringable|null $conversationId): static;

    abstract public function buildPrompt(
        string|Stringable $input,
        string|Stringable|null $conversationId
    ): string|Stringable;

    public function count(): int
    {
        return count($this->conversations);
    }
}

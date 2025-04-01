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

use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Utils\MessageUtil;

class ChatCompletionChoice
{
    public function __construct(
        public MessageInterface $message,
        public ?int $index = null,
        public ?string $logprobs = null,
        public ?string $finishReason = null
    ) {}

    public static function fromArray(array $choice): self
    {
        $message = $choice['message'] ?? [];
        if (isset($choice['delta'])) {
            $message = [
                'role' => $choice['delta']['role'] ?? 'assistant',
                'content' => $choice['delta']['content'] ?? '',
                'reasoning_content' => $choice['delta']['reasoning_content'] ?? null,
                'tool_calls' => $choice['delta']['tool_calls'] ?? [],
            ];
        }

        return new self(MessageUtil::createFromArray($message), $choice['index'] ?? null, $choice['logprobs'] ?? null, $choice['finish_reason'] ?? null);
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function getLogprobs(): ?string
    {
        return $this->logprobs;
    }

    public function getFinishReason(): ?string
    {
        return $this->finishReason;
    }

    public function isFinishedByToolCall(): bool
    {
        return $this->getFinishReason() === 'tool_calls';
    }

    public function setMessage(MessageInterface $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function setIndex(?int $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function setLogprobs(?string $logprobs): self
    {
        $this->logprobs = $logprobs;
        return $this;
    }

    public function setFinishReason(?string $finishReason): self
    {
        $this->finishReason = $finishReason;
        return $this;
    }
}

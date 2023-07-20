<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


use Hyperf\Odin\Apis\OpenAI\Message;
use Hyperf\Odin\Message\MessageInterface;

class ChatCompletionChoice
{

    public function __construct(public MessageInterface $message, public ?int $index = null, public ?string $logprobs = null, public ?string $finishReason = null)
    {
    }

    public static function fromArray(array $choice): static
    {
        return new static(Message::fromArray($choice['message']), $choice['index'] ?? null, $choice['logprobs'] ?? null, $choice['finish_reason'] ?? null);
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

}
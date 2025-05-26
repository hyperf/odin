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

namespace Hyperf\Odin\Event;

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;

class AfterChatCompletionsStreamEvent
{
    public ChatCompletionRequest $completionRequest;

    public ChatCompletionResponse $completionResponse;

    public float $duration;

    public float $firstResponseDuration;

    public function __construct(
        ChatCompletionRequest $completionRequest,
        float $firstResponseDuration,
    ) {
        $this->completionRequest = $completionRequest;
        $this->firstResponseDuration = $firstResponseDuration;
    }

    public function getCompletionRequest(): ChatCompletionRequest
    {
        return $this->completionRequest;
    }

    public function setCompletionRequest(ChatCompletionRequest $completionRequest): void
    {
        $this->completionRequest = $completionRequest;
    }

    public function getCompletionResponse(): ChatCompletionResponse
    {
        return $this->completionResponse;
    }

    public function setCompletionResponse(ChatCompletionResponse $completionResponse): void
    {
        $this->completionResponse = $completionResponse;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }
}

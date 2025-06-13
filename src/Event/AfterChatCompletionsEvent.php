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

class AfterChatCompletionsEvent
{
    public ChatCompletionRequest $completionRequest;

    public ChatCompletionResponse $completionResponse;

    public float $duration;

    public function __construct(
        ChatCompletionRequest $completionRequest,
        ?ChatCompletionResponse $completionResponse,
        float $duration
    ) {
        $this->setCompletionRequest($completionRequest);
        $this->setCompletionResponse($completionResponse);
        $this->duration = $duration;
    }

    public function getCompletionRequest(): ChatCompletionRequest
    {
        return $this->completionRequest;
    }

    public function setCompletionRequest(ChatCompletionRequest $completionRequest): void
    {
        $completionRequest = clone $completionRequest;
        $completionRequest->removeBigObject();
        $this->completionRequest = $completionRequest;
    }

    public function getCompletionResponse(): ChatCompletionResponse
    {
        return $this->completionResponse;
    }

    public function setCompletionResponse(?ChatCompletionResponse $completionResponse): void
    {
        if (! $completionResponse) {
            return;
        }
        // 移除大对象属性
        $completionResponse = clone $completionResponse;
        $completionResponse->removeBigObject();
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

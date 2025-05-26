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

class AfterChatCompletionsStreamEvent extends AfterChatCompletionsEvent
{
    public float $firstResponseDuration;

    public function __construct(
        ChatCompletionRequest $completionRequest,
        float $firstResponseDuration,
    ) {
        $this->firstResponseDuration = $firstResponseDuration;
        parent::__construct($completionRequest, null, 0);
    }

    public function getFirstResponseDuration(): float
    {
        return $this->firstResponseDuration;
    }
}

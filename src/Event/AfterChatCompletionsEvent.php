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
    public function __construct(
        public ChatCompletionRequest $completionRequest,
        public ChatCompletionResponse $completionResponse,
        public float $duration
    ) {}
}

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

namespace Hyperf\Odin\Message;

class MessageBuffer
{
    protected SystemMessage $systemMessage;

    protected UserMessage $lastUserMessage;

    protected array $previousMessages = [];

    public function getMessages(): array
    {
        return array_merge([$this->systemMessage], $this->previousMessages, [$this->lastUserMessage]);
    }
}

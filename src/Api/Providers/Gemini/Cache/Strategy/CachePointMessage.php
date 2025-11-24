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

namespace Hyperf\Odin\Api\Providers\Gemini\Cache\Strategy;

use Hyperf\Odin\Contract\Message\MessageInterface;

class CachePointMessage
{
    private mixed $originMessage;

    private string $hash;

    private int $tokens;

    public function __construct(mixed $originMessage, int $tokens)
    {
        $this->originMessage = $originMessage;
        $this->tokens = $tokens;
        $this->getHash();
    }

    public function getOriginMessage(): mixed
    {
        return $this->originMessage;
    }

    public function getHash(): string
    {
        if (! empty($this->hash)) {
            return $this->hash;
        }

        if ($this->originMessage instanceof MessageInterface) {
            $this->hash = $this->originMessage->getHash();
        } else {
            $this->hash = md5(serialize($this->originMessage));
        }
        return $this->hash;
    }

    public function getTokens(): int
    {
        return $this->tokens;
    }
}

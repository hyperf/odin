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

use Hyperf\Odin\Apis\OpenAI\Response\FunctionCall;

class AssistantMessage extends AbstractMessage
{
    protected Role $role = Role::Assistant;

    protected ?FunctionCall $functionCall;

    public function __construct(string $content, ?FunctionCall $functionCall = null)
    {
        parent::__construct($content);
        $this->functionCall = $functionCall;
    }

    public static function fromArray(array $message): static
    {
        return new static($message['content'] ?? '', FunctionCall::fromArray($message['function_call'] ?? []));
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'function_call' => $this->functionCall->toArray(),
        ];
    }

    public function getFunctionCall(): ?FunctionCall
    {
        return $this->functionCall;
    }

    public function setFunctionCall(FunctionCall $functionCall): static
    {
        $this->functionCall = $functionCall;
        return $this;
    }
}

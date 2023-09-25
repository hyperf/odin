<?php

namespace Hyperf\Odin\Message;


class AssistantMessage extends AbstractMessage
{

    protected Role $role = Role::Assistant;
    protected array $functionCall = [];

    public function __construct(string $content, array $functionCall)
    {
        parent::__construct($content);
        $this->functionCall = $functionCall;
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'function_call' => $this->functionCall,
        ];
    }

    public function getFunctionCall(): array
    {
        return $this->functionCall;
    }

    public function setFunctionCall(array $functionCall): static
    {
        $this->functionCall = $functionCall;
        return $this;
    }

}
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

namespace Hyperf\Odin\Api\OpenAI\Response;

use Hyperf\Contract\Arrayable;

class ToolCall implements Arrayable
{
    public function __construct(
        protected string $name,
        protected array $arguments,
        protected string $id,
        protected string $type = 'function',
        protected string $streamArguments = '',
    ) {}

    public static function fromArray(array $toolCalls): array
    {
        $toolCallsResult = [];
        foreach ($toolCalls as $toolCall) {
            if (! isset($toolCall['function'])) {
                return [];
            }
            $function = $toolCall['function'];
            if (isset($function['arguments'])) {
                $arguments = json_decode($function['arguments'], true);
            } else {
                return [];
            }
            if (! is_array($arguments)) {
                $arguments = [];
            }
            $name = $function['name'] ?? '';
            $id = $toolCall['id'] ?? '';
            $type = $toolCall['type'] ?? 'function';
            $static = new static($name, $arguments, $id, $type, $function['arguments']);
            $toolCallsResult[] = $static;
        }
        return $toolCallsResult;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'function' => [
                'name' => $this->getName(),
                'arguments' => $this->getSerializedArguments(),
            ],
            'type' => $this->getType(),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getArguments(): array
    {
        if (! empty($this->streamArguments)) {
            $arguments = json_decode($this->streamArguments, true);
            return is_array($arguments) ? $arguments : [];
        }
        return $this->arguments;
    }

    public function getSerializedArguments(): string
    {
        return json_encode($this->getArguments(), JSON_UNESCAPED_UNICODE);
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStreamArguments(): string
    {
        return $this->streamArguments;
    }

    public function appendStreamArguments(string $arguments): void
    {
        $this->streamArguments .= $arguments;
    }
}

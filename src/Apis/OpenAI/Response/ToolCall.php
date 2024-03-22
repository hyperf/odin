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

namespace Hyperf\Odin\Apis\OpenAI\Response;

use Hyperf\Contract\Arrayable;

class ToolCall implements Arrayable
{

    /**
     * @param string $name
     * @param array $arguments
     * @param bool $shouldFix Sometimes the API will return a wrong function call. If this flag is true will attempt to fix that.
     */
    public function __construct(protected string $name, protected array $arguments, protected string $id)
    {
    }

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
            $id = $function['id'] ?? '';
            $static = new static($name, $arguments, $id);
            $toolCallsResult[] = $static;
        }
        return $toolCallsResult;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'arguments' => $this->getArguments(),
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
        return $this->arguments;
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
}

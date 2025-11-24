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

namespace Hyperf\Odin\Api\Response;

use Hyperf\Contract\Arrayable;

class ToolCall implements Arrayable
{
    /**
     * Metadata for provider-specific extensions (e.g., Gemini thought signatures).
     */
    protected array $metadata = [];

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
            $instance = new self($name, $arguments, $id, $type, $function['arguments']);

            // Preserve thought signature if present (Gemini-specific)
            if (isset($toolCall['thought_signature'])) {
                $instance->setThoughtSignature($toolCall['thought_signature']);
            }

            $toolCallsResult[] = $instance;
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

    public function toArrayWithStream(): array
    {
        return [
            'id' => $this->getId(),
            'function' => [
                'name' => $this->getName(),
                'arguments' => $this->getStreamArguments(),
            ],
            'type' => $this->getType(),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
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
        if (! empty($this->streamArguments)) {
            return $this->streamArguments;
        }
        $arguments = json_encode($this->getArguments(), JSON_UNESCAPED_UNICODE);
        if ($arguments === '[]') {
            $arguments = '{}';
        }
        return $arguments ?: '{}';
    }

    public function setArguments(array $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getStreamArguments(): string
    {
        return $this->streamArguments;
    }

    public function getOriginalArguments(): string
    {
        return $this->streamArguments;
    }

    public function appendStreamArguments(string $arguments): void
    {
        $this->streamArguments .= $arguments;
    }

    /**
     * Get metadata value.
     */
    public function getMetadata(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Set metadata value.
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get all metadata.
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get thought signature (Gemini-specific).
     * Thought signatures are used to preserve reasoning context across multi-turn interactions.
     *
     * @see https://ai.google.dev/gemini-api/docs/thought-signatures
     */
    public function getThoughtSignature(): ?string
    {
        return $this->getMetadata('thought_signature');
    }

    /**
     * Set thought signature (Gemini-specific).
     */
    public function setThoughtSignature(?string $thoughtSignature): self
    {
        return $this->setMetadata('thought_signature', $thoughtSignature);
    }
}

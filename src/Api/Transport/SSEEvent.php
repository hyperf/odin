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

namespace Hyperf\Odin\Api\Transport;

use JsonSerializable;

class SSEEvent implements JsonSerializable
{
    private string $event;

    private mixed $data;

    private ?string $id;

    private ?int $retry;

    public function __construct(
        mixed $data = '',
        string $event = 'message',
        ?string $id = null,
        ?int $retry = null
    ) {
        $this->data = $data;
        $this->event = $event;
        $this->id = $id;
        $this->retry = $retry;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['data'] ?? '',
            $data['event'] ?? 'message',
            $data['id'] ?? null,
            isset($data['retry']) ? (int) $data['retry'] : null
        );
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getRetry(): ?int
    {
        return $this->retry;
    }

    public function setRetry(?int $retry): self
    {
        $this->retry = $retry;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'id' => $this->id,
            'retry' => $this->retry,
        ];
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function format(): string
    {
        $result = '';

        if ($this->event !== 'message') {
            $result .= "event: {$this->event}\n";
        }

        $data = $this->data;
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        if (is_string($data)) {
            $dataLines = explode("\n", $data);
            foreach ($dataLines as $line) {
                $result .= "data: {$line}\n";
            }
        } else {
            $result .= "data: \n";
        }

        if ($this->id !== null) {
            $result .= "id: {$this->id}\n";
        }

        if ($this->retry !== null) {
            $result .= "retry: {$this->retry}\n";
        }

        return $result . "\n";
    }
}

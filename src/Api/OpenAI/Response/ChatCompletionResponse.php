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

use Generator;
use Hyperf\Odin\Exception\RuntimeException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;
use Stringable;

class ChatCompletionResponse extends AbstractResponse implements Stringable
{
    protected ?string $id = null;

    protected ?string $object = null;

    protected ?int $created = null;

    protected ?string $model = null;

    protected ?array $choices = [];

    protected ?Usage $usage = null;

    protected bool $isChunked = false;

    public function __toString(): string
    {
        return trim($this->getChoices()[0]?->getMessage()?->getContent() ?: '');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(?string $object): static
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    public function setCreated(?int $created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getFirstChoice(): ?ChatCompletionChoice
    {
        return $this->choices[0] ?? null;
    }

    /**
     * @return null|ChatCompletionChoice[]
     */
    public function getChoices(): ?array
    {
        return $this->choices;
    }

    public function setChoices(?array $choices): static
    {
        $this->choices = $choices;
        return $this;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(?Usage $usage): static
    {
        $this->usage = $usage;
        return $this;
    }

    public function isChunked(): bool
    {
        return $this->isChunked;
    }

    public function getStreamIterator(): Generator
    {
        while (! $this->originResponse->getBody()->eof()) {
            $line = $this->readLine($this->originResponse->getBody());

            if (! str_starts_with($line, 'data:')) {
                continue;
            }
            $data = trim(substr($line, strlen('data:')));
            if (str_starts_with('[DONE]', $data)) {
                break;
            }
            $content = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Invalid JSON response | ' . $line);
            }
            if (isset($content['error'])) {
                throw new RuntimeException('Steam Error | ' . $content['error']);
            }
            $this->setId($content['id'] ?? null);
            $this->setObject($content['object'] ?? null);
            $this->setCreated($content['created'] ?? null);
            $this->setModel($content['model'] ?? null);
            if (empty($content['choices'])) {
                continue;
            }
            foreach ($content['choices'] as $choice) {
                yield ChatCompletionChoice::fromArray($choice);
            }
        }
    }

    public function setOriginResponse(PsrResponseInterface $originResponse): static
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->parseContent();
        return $this;
    }

    protected function parseContent(): static
    {
        if ($this->originResponse->hasHeader('Transfer-Encoding')
            && $this->originResponse->getHeaderLine('Transfer-Encoding') === 'chunked') {
            $this->isChunked = true;
            return $this;
        }
        $this->content = $this->originResponse->getBody()->getContents();
        $content = json_decode($this->content, true);
        if (isset($content['id'])) {
            $this->setId($content['id']);
        }
        if (isset($content['object'])) {
            $this->setObject($content['object']);
        }
        if (isset($content['created'])) {
            $this->setCreated($content['created']);
        }
        if (isset($content['model'])) {
            $this->setModel($content['model']);
        }
        if (isset($content['choices'])) {
            $this->setChoices($this->buildChoices($content['choices']));
        }
        if (isset($content['usage'])) {
            $this->setUsage(Usage::fromArray($content['usage']));
        }
        return $this;
    }

    protected function buildChoices(mixed $choices): array
    {
        $result = [];
        foreach ($choices as $choice) {
            $result[] = ChatCompletionChoice::fromArray($choice);
        }
        return $result;
    }

    private function readLine(StreamInterface $stream): string
    {
        $buffer = '';
        while (! $stream->eof()) {
            if ('' === ($byte = $stream->read(1))) {
                return $buffer;
            }
            $buffer .= $byte;
            if ($byte === "\n") {
                break;
            }
        }
        return $buffer;
    }
}

<?php

namespace Hyperf\Odin\Api\OpenAI\Response;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class TextCompletionResponse extends AbstractResponse
{

    protected bool $success = false;
    protected ?string $content = null;
    protected ?string $id = null;
    protected ?string $object = null;
    protected ?string $created = null;
    protected array|null $choices = [];
    protected ?Usage $usage = null;

    public function setOriginResponse(PsrResponseInterface $originResponse): static
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->content = $originResponse->getBody()->getContents();
        $this->parseContent();
        return $this;
    }

    protected function parseContent(): static
    {
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
            $result[] = TextCompletionChoice::fromArray($choice);
        }
        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent($content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId($id): static
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject($object): static
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?string
    {
        return $this->created;
    }

    public function setCreated($created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getChoices(): ?array
    {
        return $this->choices;
    }

    public function setChoices($choices): static
    {
        $this->choices = $choices;
        return $this;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(Usage $usage): static
    {
        $this->usage = $usage;
        return $this;
    }
}
<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;

class ChatCompletionResponse extends AbstractResponse
{

    protected ?string $id = null;
    protected ?string $object = null;
    protected ?string $created = null;
    protected ?string $model = null;
    protected array|null $choices = [];
    protected ?Usage $usage = null;

    public function __toString(): string
    {
        return $this?->getChoices()[0]?->getMessage()?->getContent() ?: '';
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

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel($model): static
    {
        $this->model = $model;
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
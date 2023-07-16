<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Psr\Http\Message\ResponseInterface;

class Response
{

    protected bool $success = false;

    protected ?string $content = null;

    protected ResponseInterface $originResponse;

    protected ?string $id = null;
    protected ?string $object = null;
    protected ?string $created = null;
    protected ?string $model = null;
    protected array|null $choices = [];
    protected ?Usage $usage = null;

    public function __construct(ResponseInterface $response)
    {
        $this->setOriginResponse($response);
    }
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
            $result[] = Choice::fromArray($choice);
        }
        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOriginResponse(): ResponseInterface
    {
        return $this->originResponse;
    }

    public function setOriginResponse(ResponseInterface $originResponse): static
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->content = $originResponse->getBody()->getContents();
        $this->parseContent();
        return $this;
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
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

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class TextCompletionResponse extends AbstractResponse
{
    protected bool $success = false;

    protected ?string $content = null;

    protected ?string $id = null;

    protected ?string $object = null;

    protected ?int $created = null;

    /**
     * @var null|TextCompletionChoice[]
     */
    protected ?array $choices = [];

    protected ?Usage $usage = null;

    public function getFirstChoice(): ?TextCompletionChoice
    {
        return $this->choices[0] ?? null;
    }

    public function setOriginResponse(PsrResponseInterface $originResponse): self
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->content = $originResponse->getBody()->getContents();
        $this->parseContent();
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject($object): self
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(null|int|string $created): self
    {
        $this->created = (int) $created;
        return $this;
    }

    /**
     * @return null|TextCompletionChoice[]
     */
    public function getChoices(): ?array
    {
        return $this->choices;
    }

    public function setChoices($choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(?Usage $usage): self
    {
        $this->usage = $usage;
        return $this;
    }

    protected function parseContent(): self
    {
        $content = json_decode($this->content, true);
        if (isset($content['content'])) {
            $this->parseContentByText($content['content']);
            return $this;
        }
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

    private function parseContentByText(string $text): void
    {
        $choices = [
            [
                'text' => $text,
                'index' => 0,
                'logprobs' => null,
                'finish_reason' => 'stop',
            ],
        ];
        $this->setChoices($this->buildChoices($choices));
    }
}

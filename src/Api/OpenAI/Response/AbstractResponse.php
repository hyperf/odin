<?php

namespace Hyperf\Odin\Api\OpenAI\Response;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

abstract class AbstractResponse implements ResponseInterface
{

    protected PsrResponseInterface $originResponse;

    protected bool $success = false;

    protected ?string $content = null;

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
        $this->parseContent();
        return $this;
    }

    public function __construct(?PsrResponseInterface $response = null)
    {
        $response && $this->setOriginResponse($response);
    }

    public function getOriginResponse(): PsrResponseInterface
    {
        return $this->originResponse;
    }

    public function setOriginResponse(PsrResponseInterface $originResponse): static
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->content = $originResponse->getBody()->getContents();
        $this->parseContent();
        return $this;
    }

    abstract protected function parseContent(): static;

}
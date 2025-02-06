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

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

abstract class AbstractResponse implements ResponseInterface
{
    protected PsrResponseInterface $originResponse;

    protected bool $success = false;

    protected ?string $content = null;

    public function __construct(?PsrResponseInterface $response = null)
    {
        $response && $this->setOriginResponse($response);
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
        $this->parseContent();
        return $this;
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

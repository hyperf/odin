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

use Hyperf\Odin\Contract\Api\Response\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractResponse implements ResponseInterface
{
    protected PsrResponseInterface $originResponse;

    protected ?LoggerInterface $logger = null;

    protected bool $success = false;

    protected ?string $content = null;

    /**
     * 使用统计
     */
    protected ?Usage $usage = null;

    public function __construct(PsrResponseInterface $response, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->setOriginResponse($response);
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
        $this->parseContent();
        return $this;
    }

    public function getOriginResponse(): PsrResponseInterface
    {
        return $this->originResponse;
    }

    public function setOriginResponse(PsrResponseInterface $originResponse): self
    {
        $this->originResponse = $originResponse;
        $this->success = $originResponse->getStatusCode() === 200;
        $this->parseContent();
        return $this;
    }

    /**
     * 获取使用统计
     */
    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    /**
     * 设置使用统计
     */
    public function setUsage(?Usage $usage): self
    {
        $this->usage = $usage;
        return $this;
    }

    public function removeBigObject(): void
    {
        unset($this->originResponse, $this->logger);
    }

    abstract protected function parseContent(): self;
}

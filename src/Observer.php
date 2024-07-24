<?php

namespace Hyperf\Odin;


use Stringable;

class Observer
{

    protected bool $debug = false;

    public function __construct(public Logger $logger)
    {
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        if (! $this->isDebug()) {
            return;
        }
        $this->logger->debug($message, $context);
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;
        return $this;
    }

}
<?php

namespace Hyperf\Odin\VectorStore\Qdrant;

class Config implements ConfigInterface
{
    public function getScheme(): string
    {
        return 'http';
    }

    public function getHost(): string
    {
        return '127.0.0.1';
    }

    public function getPort(): int
    {
        return 6333;
    }
}
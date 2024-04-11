<?php

namespace Hyperf\Odin\Api\RWKV;


class RWKVConfig
{

    public ?string $baseUrl = null;

    public function __construct(
        string $baseUrl = 'http://10.209.192.4:8000/'
    ) {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
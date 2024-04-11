<?php

namespace Hyperf\Odin\Api\Skylark;


class SkylarkConfig
{

    public function __construct(
        public string $ak,
        public string $sk,
        public string $endpoint,
        public string $host = 'https://maas-api.ml-platform-cn-beijing.volces.com',
        public string $region = 'cn-beijing',
        public string $service = 'ml_maas',
    ) {
    }

    public function getAk(): string
    {
        return $this->ak;
    }

    public function getSk(): string
    {
        return $this->sk;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getHost(bool $withSchema = true): string
    {
        if ($withSchema) {
            return $this->host;
        } else {
            return str_replace(['http://', 'https://'], '', $this->host);
        }
    }

    public function getService(): string
    {
        return $this->service;
    }
}
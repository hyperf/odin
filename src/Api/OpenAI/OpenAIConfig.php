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

namespace Hyperf\Odin\Api\OpenAI;

class OpenAIConfig
{
    public ?string $baseUrl = null;

    protected ?string $apiKey = null;

    protected ?string $organization = null;

    public function __construct(
        ?string $apiKey = null,
        ?string $organization = null,
        string $baseUrl = 'https://api.openai.com/'
    ) {
        $this->apiKey = $apiKey;
        $this->organization = $organization;
        $this->baseUrl = $baseUrl;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}

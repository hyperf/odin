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

namespace Hyperf\Odin\Api\Providers\OpenAI;

use Hyperf\Odin\Contract\Api\ConfigInterface;

class OpenAIConfig implements ConfigInterface
{
    public string $baseUrl;

    public string $apiKey;

    protected string $organization;

    /**
     * 是否跳过API Key验证
     */
    protected bool $skipApiKeyValidation = false;

    public function __construct(
        string $apiKey,
        string $organization = '',
        string $baseUrl = 'https://api.openai.com',
        bool $skipApiKeyValidation = false,
    ) {
        $this->apiKey = $apiKey;
        $this->organization = $organization;
        $this->baseUrl = $baseUrl;
        $this->skipApiKeyValidation = $skipApiKeyValidation;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function shouldSkipApiKeyValidation(): bool
    {
        return $this->skipApiKeyValidation;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            $config['api_key'] ?? '',
            $config['organization'] ?? '',
            $config['base_url'] ?? 'https://api.openai.com',
            $config['skip_api_key_validation'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'organization' => $this->organization,
            'base_url' => $this->baseUrl,
            'skip_api_key_validation' => $this->skipApiKeyValidation,
        ];
    }
}

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

namespace Hyperf\Odin\Api\Providers\DeepSeek;

use Hyperf\Odin\Contract\Api\ConfigInterface;

/**
 * DeepSeek API configuration.
 *
 * @see https://api-docs.deepseek.com/
 */
class DeepSeekConfig implements ConfigInterface
{
    public string $baseUrl;

    public string $apiKey;

    /**
     * Whether to skip API key validation.
     */
    protected bool $skipApiKeyValidation = false;

    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.deepseek.com',
        bool $skipApiKeyValidation = false,
    ) {
        $this->apiKey = $apiKey;
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

    public function shouldSkipApiKeyValidation(): bool
    {
        return $this->skipApiKeyValidation;
    }

    public static function fromArray(array $config): self
    {
        return new self(
            $config['api_key'] ?? '',
            $config['base_url'] ?? 'https://api.deepseek.com',
            $config['skip_api_key_validation'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'skip_api_key_validation' => $this->skipApiKeyValidation,
        ];
    }
}

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

namespace Hyperf\Odin\Api\Providers\Gemini;

use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;

class Client extends AbstractClient
{
    public function __construct(GeminiConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }
        parent::__construct($config, $requestOptions, $logger);
    }

    /**
     * Build chat completions API URL
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/chat/completions';
    }

    /**
     * Build embeddings API URL
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->getBaseUri() . '/embeddings';
    }

    /**
     * Build text completions API URL
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->getBaseUri() . '/completions';
    }

    /**
     * Get authentication headers
     */
    protected function getAuthHeaders(): array
    {
        $headers = [];
        /** @var GeminiConfig $config */
        $config = $this->config;

        if ($config->getApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $config->getApiKey();
        }

        return $headers;
    }
}

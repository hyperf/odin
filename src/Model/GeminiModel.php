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

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;

class GeminiModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    /**
     * Get client instance.
     */
    protected function getClient(): ClientInterface
    {
        // Process API base URL to ensure it contains the correct version path
        $config = $this->config;
        $this->processApiBaseUrl($config);

        // Use ClientFactory to create Gemini client
        return ClientFactory::createClient(
            'gemini',
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    /**
     * Get API version path
     * Gemini uses OpenAI-compatible API, so no version path is needed.
     */
    protected function getApiVersionPath(): string
    {
        return '';
    }
}

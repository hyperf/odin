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

use Hyperf\Odin\Api\Providers\AbstractApi;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use Psr\Log\LoggerInterface;

/**
 * DeepSeek API provider.
 *
 * Supports both standard chat and reasoning (thinking) mode.
 *
 * @see https://api-docs.deepseek.com/
 */
class DeepSeek extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients = [];

    public function getClient(DeepSeekConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null): Client
    {
        // Check API key unless configured to skip validation
        if (empty($config->getApiKey()) && ! $config->shouldSkipApiKeyValidation()) {
            throw new LLMInvalidApiKeyException('API key cannot be empty', null, 'DeepSeek');
        }

        if (empty($config->getBaseUrl())) {
            throw new LLMInvalidEndpointException('Base URL cannot be empty', null, $config->getBaseUrl());
        }
        $requestOptions = $requestOptions ?? new ApiOptions();

        $key = md5(json_encode($config->toArray()) . json_encode($requestOptions->toArray()));
        if (($this->clients[$key] ?? null) instanceof Client) {
            return $this->clients[$key];
        }

        $client = new Client($config, $requestOptions, $logger);

        $this->clients[$key] = $client;
        return $this->clients[$key];
    }
}

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

namespace Hyperf\Odin\Api\Providers\DashScope;

use Hyperf\Odin\Api\Providers\AbstractApi;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use Psr\Log\LoggerInterface;

class DashScope extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients = [];

    public function getClient(
        DashScopeConfig $config,
        ?ApiOptions $requestOptions = null,
        ?LoggerInterface $logger = null
    ): Client {
        // 检查 API Key
        if (empty($config->getApiKey()) && ! $config->shouldSkipApiKeyValidation()) {
            throw new LLMInvalidApiKeyException('DashScope API密钥不能为空', null, 'DashScope');
        }

        if (empty($config->getBaseUrl())) {
            throw new LLMInvalidEndpointException('基础URL不能为空', null, $config->getBaseUrl());
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

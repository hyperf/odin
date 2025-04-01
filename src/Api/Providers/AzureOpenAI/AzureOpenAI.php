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

namespace Hyperf\Odin\Api\Providers\AzureOpenAI;

use Hyperf\Odin\Api\Providers\AbstractApi;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use Psr\Log\LoggerInterface;

class AzureOpenAI extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients = [];

    public function getClient(AzureOpenAIConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null): Client
    {
        if (empty($config->getApiKey())) {
            throw new LLMInvalidApiKeyException('API密钥不能为空', null, 'AzureOpenAI');
        }
        if (empty($config->getBaseUrl())) {
            throw new LLMInvalidEndpointException('基础URL不能为空', null, $config->getBaseUrl());
        }
        if (empty($config->getApiVersion())) {
            throw new LLMConfigurationException('API版本不能为空');
        }
        if (empty($config->getDeploymentName())) {
            throw new LLMConfigurationException('部署名称不能为空');
        }

        $requestOptions = $requestOptions ?? new ApiOptions();

        $key = md5(json_encode($config->toArray()) . json_encode($requestOptions->toArray()));
        if (($this->clients[$key] ?? null) instanceof Client) {
            return $this->clients[$key];
        }

        $client = new Client($config, $requestOptions, $logger);
        $this->clients[$key] = $client;
        return $client;
    }
}

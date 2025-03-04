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

use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;

class Client extends AbstractClient
{
    protected AzureOpenAIConfig $azureConfig;

    public function __construct(AzureOpenAIConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }
        $this->azureConfig = $config;
        parent::__construct($config, $requestOptions, $logger);
    }

    /**
     * 构建聊天补全API的URL.
     */
    protected function buildChatCompletionsUrl(): string
    {
        return $this->buildDeploymentPath() . '/chat/completions?api-version=' . $this->azureConfig->getApiVersion();
    }

    /**
     * 构建嵌入API的URL.
     */
    protected function buildEmbeddingsUrl(): string
    {
        return $this->buildDeploymentPath() . '/embeddings?api-version=' . $this->azureConfig->getApiVersion();
    }

    /**
     * 构建文本补全API的URL.
     */
    protected function buildCompletionsUrl(): string
    {
        return $this->buildDeploymentPath() . '/completions?api-version=' . $this->azureConfig->getApiVersion();
    }

    /**
     * 获取认证头信息.
     */
    protected function getAuthHeaders(): array
    {
        $headers = [];

        if ($this->config->getApiKey()) {
            $headers['api-key'] = $this->config->getApiKey();
        }

        return $headers;
    }

    /**
     * 构建部署路径.
     */
    protected function buildDeploymentPath(): string
    {
        return $this->getBaseUri() . '/openai/deployments/' . $this->azureConfig->getDeploymentName();
    }
}

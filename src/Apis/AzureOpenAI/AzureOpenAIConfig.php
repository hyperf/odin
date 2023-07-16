<?php

namespace Hyperf\Odin\Apis\AzureOpenAI;


use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;

class AzureOpenAIConfig extends OpenAIConfig
{

    protected ?string $apiVersion = null;
    protected ?string $deploymentName = null;

    public function __construct(
        string $apiKey = null,
        string $organization = null,
        string $baseUrl = 'https://example-endpoint.openai.azure.com/',
        string $apiVersion = '2023-05-15',
        string $deploymentName = ''
    ) {
        parent::__construct($apiKey, $organization, $baseUrl);
        $this->apiVersion = $apiVersion;
        $this->deploymentName = $deploymentName;
    }

    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    public function getDeploymentName(): ?string
    {
        return $this->deploymentName;
    }

}
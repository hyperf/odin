<?php

namespace Hyperf\Odin\Apis\AzureOpenAI;


use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class AzureOpenAIClientFactory
{

    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $azureConfig = new AzureOpenAIConfig(apiKey: $config->get('odin.azure_openai.api_key'), baseUrl: $config->get('odin.azure_openai.api_base'), apiVersion: $config->get('odin.azure_openai.api_version'), deploymentName: $config->get('odin.azure_openai.deployment_name'),);
        return (new AzureOpenAI())->getClient($azureConfig);
    }

}
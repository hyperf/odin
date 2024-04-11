<?php

namespace Hyperf\Odin\Api\OpenAI;


use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class OpenAIClientFactory
{

    public function __invoke(ContainerInterface $container): Client
    {
        $config = $container->get(ConfigInterface::class);
        $openAIConfig = new OpenAIConfig(apiKey: $config->get('odin.openai.api_key'),);
        $openAI = new OpenAI();
        return $openAI->getClient($openAIConfig);
    }

}
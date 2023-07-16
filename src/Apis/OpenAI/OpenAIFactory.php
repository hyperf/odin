<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Psr\Container\ContainerInterface;

class OpenAIFactory
{

    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(OpenAIConfig::class);
        $openAI = new OpenAI();
        $openAI->getClient($config);
        return $openAI;
    }

}
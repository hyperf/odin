<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Psr\Container\ContainerInterface;

class OpenAIFactory
{

    public function __invoke(ContainerInterface $container): Client
    {
        $config = new OpenAIConfig(\Hyperf\Support\env('OPENAI_API_KEY_FOR_TEST'),);
        $openAI = new OpenAI();
        return $openAI->getClient($config);
    }

}
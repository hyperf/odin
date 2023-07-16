<?php

namespace Hyperf\Odin\Apis\AzureOpenAI;


use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;

class AzureOpenAI extends OpenAI
{
    public function getClient(AzureOpenAIConfig|OpenAIConfig $config): Client|OpenAIClient
    {
        if ($config->getApiKey() && isset($this->clients[$config->getApiKey()])) {
            return $this->clients[$config->getApiKey()];
        }
        $client = new Client($config);
        $this->clients[$config->getApiKey()] = $client;
        return $client;
    }

}
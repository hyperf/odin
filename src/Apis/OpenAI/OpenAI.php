<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Apis\AbstractApi;

class OpenAI extends AbstractApi
{

    /**
     * @var Client[]
     */
    protected array $clients = [

    ];

    public function getClient(OpenAIConfig $config)
    {
        if ($config->getApiKey() && isset($this->clients[$config->getApiKey()])) {
            return $this->clients[$config->getApiKey()];
        }
        $client = new Client($config);
        $this->clients[$config->getApiKey()] = $client;
        return $client;
    }

}
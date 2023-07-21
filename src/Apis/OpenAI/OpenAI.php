<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Apis\AbstractApi;
use Hyperf\Odin\Logger;

class OpenAI extends AbstractApi
{

    /**
     * @var Client[]
     */
    protected array $clients
        = [

        ];

    public function getClient(OpenAIConfig $config): Client
    {
        if ($config->getApiKey() && isset($this->clients[$config->getApiKey()])) {
            return $this->clients[$config->getApiKey()];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getApiKey()] = $client;
        return $client;
    }

}
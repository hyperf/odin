<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Apis\AbstractApi;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Apis\OpenAI\Response\TextCompletionResponse;

class OpenAI extends AbstractApi
{

    /**
     * @var Client[]
     */
    protected array $clients = [

    ];

    public function getClient(OpenAIConfig $config): Client
    {
        if ($config->getApiKey() && isset($this->clients[$config->getApiKey()])) {
            return $this->clients[$config->getApiKey()];
        }
        $client = new Client($config);
        $this->clients[$config->getApiKey()] = $client;
        return $client;
    }

}
<?php

namespace Hyperf\Odin\Apis\OpenAI;


use Hyperf\Odin\Apis\AbstractApi;

/**
 * @method Response chat(array $messages, string $model, float $temperature = 0.9, int $maxTokens = 200)
 */
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

    public function __call(string $name, array $arguments)
    {
        $client = $this->getClient($arguments[0]);
        return $client->$name(...array_slice($arguments, 1));
    }

}
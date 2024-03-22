<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Odin\Apis\AzureOpenAI;

use Hyperf\Odin\Logger;

class AzureOpenAI
{
    /**
     * @var Client[]
     */
    protected array $clients
        = [];

    public function getClient(AzureOpenAIConfig $config, string $modelName): Client
    {
        $apiKey = $config->getApiKey();
        $storageKey = $apiKey . '-' . $modelName;
        if ($apiKey && isset($this->clients[$apiKey])) {
            return $this->clients[$storageKey];
        }
        $client = new Client($config, new Logger(), $modelName);
        $this->clients[$storageKey] = $client;
        return $client;
    }
}

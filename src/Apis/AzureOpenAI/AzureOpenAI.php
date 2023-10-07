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
        if ($config->getApiKey($modelName) && isset($this->clients[$config->getApiKey($modelName)])) {
            return $this->clients[$config->getApiKey($modelName)];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getApiKey($modelName)] = $client;
        return $client;
    }
}

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

namespace Hyperf\Odin\Api\Chatglm;

use Hyperf\Odin\Api\AbstractApi;
use Hyperf\Odin\Logger;

class Chatglm extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients
        = [];

    public function getClient(ChatglmConfig $config, string $modelName): Client
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

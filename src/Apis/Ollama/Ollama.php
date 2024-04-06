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

namespace Hyperf\Odin\Apis\Ollama;

use Hyperf\Odin\Apis\AbstractApi;
use Hyperf\Odin\Logger;

class Ollama extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients
        = [];

    public function getClient(OllamaConfig $config): Client
    {
        if ($config->getBaseUrl() && isset($this->clients[$config->getBaseUrl()])) {
            return $this->clients[$config->getBaseUrl()];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getBaseUrl()] = $client;
        return $client;
    }
}

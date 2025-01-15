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

namespace Hyperf\Odin\Api\Skylark;

use Hyperf\Odin\Api\AbstractApi;
use Hyperf\Odin\Logger;

class Skylark extends AbstractApi
{
    /**
     * @var Client[]
     */
    protected array $clients
        = [];

    public function getClient(SkylarkConfig $config): Client
    {
        if ($config->getApiKey() && isset($this->clients[$config->getApiKey()])) {
            return $this->clients[$config->getApiKey()];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getApiKey()] = $client;
        return $client;
    }
}

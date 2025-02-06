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

namespace Hyperf\Odin\Api\RWKV;

use Hyperf\Odin\Logger;

class RWKV
{
    /**
     * @var Client[]
     */
    protected array $clients
        = [
        ];

    public function getClient(RWKVConfig $config): Client
    {
        if ($config->getBaseUrl() && isset($this->clients[$config->getBaseUrl()])) {
            return $this->clients[$config->getBaseUrl()];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getBaseUrl()] = $client;
        return $client;
    }
}

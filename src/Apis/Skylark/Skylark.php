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

namespace Hyperf\Odin\Apis\Skylark;

use Hyperf\Odin\Apis\AbstractApi;
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
        if ($config->getEndpoint() && isset($this->clients[$config->getEndpoint()])) {
            return $this->clients[$config->getEndpoint()];
        }
        $client = new Client($config, new Logger());
        $this->clients[$config->getEndpoint()] = $client;
        return $client;
    }
}

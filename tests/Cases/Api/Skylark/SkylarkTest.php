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

namespace HyperfTest\Odin\Cases\Api\Skylark;

use Hyperf\Odin\Api\Skylark\Client;
use Hyperf\Odin\Api\Skylark\Skylark;
use Hyperf\Odin\Api\Skylark\SkylarkConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class SkylarkTest extends AbstractTestCase
{
    public function testGetClientWithNewConfig()
    {
        $config = new SkylarkConfig('test_api_key', 'https://custom.url/', 'test_model');
        $skylark = new Skylark();

        $client = $skylark->getClient($config);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testGetClientWithExistingConfig()
    {
        $config = new SkylarkConfig('test_api_key', 'https://custom.url/', 'test_model');
        $skylark = new Skylark();

        $client1 = $skylark->getClient($config);
        $client2 = $skylark->getClient($config);

        $this->assertSame($client1, $client2);
    }
}

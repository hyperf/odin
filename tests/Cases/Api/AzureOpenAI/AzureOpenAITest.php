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

namespace HyperfTest\Odin\Cases\Api\AzureOpenAI;

use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\AzureOpenAI\Client;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class AzureOpenAITest extends AbstractTestCase
{
    public function testGetClient()
    {
        $config = new AzureOpenAIConfig([
            'api_key' => 'test_api_key',
            'api_base' => 'https://api.example.com',
            'api_version' => 'v1',
            'deployment_name' => 'test_deployment',
        ]);

        $azureOpenAI = new AzureOpenAI();
        $client = $azureOpenAI->getClient($config, 'gpt-3.5-turbo');

        $this->assertInstanceOf(Client::class, $client);
    }
}

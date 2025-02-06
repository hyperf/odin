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

namespace HyperfTest\Odin\Cases\Api\OpenAI;

use Hyperf\Odin\Api\OpenAI\OpenAIConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class OpenAIConfigTest extends AbstractTestCase
{
    public function testConstructorWithParameters()
    {
        $config = new OpenAIConfig('test_api_key', 'test_organization', 'https://custom.url/');

        $this->assertSame('test_api_key', $config->getApiKey());
        $this->assertSame('test_organization', $config->getOrganization());
        $this->assertSame('https://custom.url/', $config->getBaseUrl());
    }

    public function testConstructorWithDefaultBaseUrl()
    {
        $config = new OpenAIConfig('test_api_key', 'test_organization');

        $this->assertSame('test_api_key', $config->getApiKey());
        $this->assertSame('test_organization', $config->getOrganization());
        $this->assertSame('https://api.openai.com/', $config->getBaseUrl());
    }

    public function testConstructorWithNullValues()
    {
        $config = new OpenAIConfig();

        $this->assertNull($config->getApiKey());
        $this->assertNull($config->getOrganization());
        $this->assertSame('https://api.openai.com/', $config->getBaseUrl());
    }
}

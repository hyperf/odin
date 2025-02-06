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

namespace HyperfTest\Odin\Cases\Api\Doubao;

use Hyperf\Odin\Api\Doubao\DoubaoConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class DoubaoConfigTest extends AbstractTestCase
{
    public function testCustomValues()
    {
        $config = new DoubaoConfig('test_api_key', 'https://custom.url/', 'test_model');

        $this->assertSame('test_api_key', $config->getApiKey());
        $this->assertSame('https://custom.url/', $config->getBaseUrl());
        $this->assertSame('test_model', $config->getModel());
    }
}

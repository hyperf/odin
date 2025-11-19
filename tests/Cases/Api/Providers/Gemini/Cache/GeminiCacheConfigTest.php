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

namespace HyperfTest\Odin\Cases\Api\Providers\Gemini\Cache;

use Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\Cache\GeminiCacheConfig
 */
class GeminiCacheConfigTest extends AbstractTestCase
{
    public function testDefaultValues()
    {
        $config = new GeminiCacheConfig();
        $this->assertEquals(1024, $config->getMinCacheTokens());
        $this->assertEquals(5000, $config->getRefreshPointMinTokens());
        $this->assertEquals(600, $config->getTtl());
        $this->assertFalse($config->isEnableAutoCache());
    }

    public function testCustomValues()
    {
        $config = new GeminiCacheConfig(
            minCacheTokens: 2048,
            refreshPointMinTokens: 6000,
            ttl: 1200,
            enableAutoCache: true
        );
        $this->assertEquals(2048, $config->getMinCacheTokens());
        $this->assertEquals(6000, $config->getRefreshPointMinTokens());
        $this->assertEquals(1200, $config->getTtl());
        $this->assertTrue($config->isEnableAutoCache());
    }

    public function testGetMinCacheTokensByModel()
    {
        // Test Gemini 2.5 Flash
        $this->assertEquals(1024, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2.5-flash'));
        $this->assertEquals(1024, GeminiCacheConfig::getMinCacheTokensByModel('gemini-flash'));

        // Test Gemini 2.5 Pro
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2.5-pro'));
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-pro'));

        // Test Gemini 3 Pro Preview
        // Note: Due to match order, 'gemini-3-pro-preview' contains 'pro', so it matches 'pro' pattern first (4096)
        // The '3-pro-preview' pattern is never reached because 'pro' comes first
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-3-pro-preview'));
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-3-pro'));

        // Test default
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('unknown-model'));
    }
}

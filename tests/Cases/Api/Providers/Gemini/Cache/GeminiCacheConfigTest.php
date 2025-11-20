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
        // Test Gemini 2.5 Flash (official requirement: 2048 tokens)
        $this->assertEquals(2048, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2.5-flash'));
        $this->assertEquals(2048, GeminiCacheConfig::getMinCacheTokensByModel('Gemini-2.5-Flash')); // Case insensitive
        $this->assertEquals(2048, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2-flash')); // Gemini 2.0 Flash
        $this->assertEquals(2048, GeminiCacheConfig::getMinCacheTokensByModel('gemini-3-flash')); // Gemini 3.0 Flash

        // Test Gemini 2.5 Pro (official requirement: 4096 tokens)
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2.5-pro'));
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('Gemini-2.5-Pro')); // Case insensitive
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-2-pro')); // Gemini 2.0 Pro
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-3-pro')); // Gemini 3.0 Pro
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('gemini-3.0-pro'));

        // Test default (use highest threshold to be safe)
        $this->assertEquals(4096, GeminiCacheConfig::getMinCacheTokensByModel('unknown-model'));
    }
}

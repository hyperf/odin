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

namespace HyperfTest\Odin\Cases;

use Hyperf\Config\Config;
use Hyperf\Odin\ModelMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(ModelMapper::class)]
class ModelMapperTest extends TestCase
{
    private ModelMapper $modelMapper;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock config with test data
        $this->config = new Config([
            'odin' => [
                'llm' => [
                    'default' => 'gpt-3.5-turbo',
                    'default_embedding' => 'text-embedding-ada-002',
                    'models' => [],
                    'model_fixed_temperature' => [
                        'gpt-4' => 0.5,
                        '%gpt-5%' => 1.0,
                        'claude-%' => 0.8,
                        '%gemini%' => 0.7,
                        'exact-model-name' => 0.9,
                    ]
                ]
            ]
        ]);

        $logger = new \Psr\Log\NullLogger();
        $this->modelMapper = new ModelMapper($this->config, $logger);
    }

    /**
     * Test exact match for fixed temperature
     */
    public function testExactMatchFixedTemperature()
    {
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        $result = $method->invoke($this->modelMapper, 'gpt-4');
        $this->assertEquals(0.5, $result);

        $result = $method->invoke($this->modelMapper, 'exact-model-name');
        $this->assertEquals(0.9, $result);
    }

    /**
     * Test wildcard pattern matching
     */
    public function testWildcardPatternMatching()
    {
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        // Test %gpt-5% pattern
        $result = $method->invoke($this->modelMapper, 'gpt-5-turbo');
        $this->assertEquals(1.0, $result);

        $result = $method->invoke($this->modelMapper, 'new-gpt-5-model');
        $this->assertEquals(1.0, $result);

        $result = $method->invoke($this->modelMapper, 'gpt-5');
        $this->assertEquals(1.0, $result);

        // Test claude-% pattern
        $result = $method->invoke($this->modelMapper, 'claude-3');
        $this->assertEquals(0.8, $result);

        $result = $method->invoke($this->modelMapper, 'claude-haiku');
        $this->assertEquals(0.8, $result);

        // Test %gemini% pattern
        $result = $method->invoke($this->modelMapper, 'google-gemini-pro');
        $this->assertEquals(0.7, $result);

        $result = $method->invoke($this->modelMapper, 'gemini-1.5');
        $this->assertEquals(0.7, $result);

        $result = $method->invoke($this->modelMapper, 'gemini');
        $this->assertEquals(0.7, $result);
    }

    /**
     * Test no match scenarios
     */
    public function testNoMatchScenarios()
    {
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        // No match for non-configured models
        $result = $method->invoke($this->modelMapper, 'unknown-model');
        $this->assertNull($result);

        $result = $method->invoke($this->modelMapper, 'gpt-3.5-turbo');
        $this->assertNull($result);

        // Pattern doesn't match
        $result = $method->invoke($this->modelMapper, 'openai-gpt4');
        $this->assertNull($result);
    }

    /**
     * Test exact match takes precedence over wildcard
     */
    public function testExactMatchPrecedence()
    {
        // Add exact match for a model that would also match wildcard
        $this->config->set('odin.llm.model_fixed_temperature.gpt-5-exact', 2.0);
        
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        // Should get exact match value, not wildcard
        $result = $method->invoke($this->modelMapper, 'gpt-5-exact');
        $this->assertEquals(2.0, $result);
    }

    /**
     * Test wildcard pattern matching method directly
     */
    public function testWildcardPatternMatchingDirect()
    {
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('matchesWildcardPattern');
        $method->setAccessible(true);

        // Test various patterns
        $this->assertTrue($method->invoke($this->modelMapper, 'gpt-5-turbo', '%gpt-5%'));
        $this->assertTrue($method->invoke($this->modelMapper, 'new-gpt-5-model', '%gpt-5%'));
        $this->assertTrue($method->invoke($this->modelMapper, 'gpt-5', '%gpt-5%'));
        
        $this->assertTrue($method->invoke($this->modelMapper, 'claude-3', 'claude-%'));
        $this->assertTrue($method->invoke($this->modelMapper, 'claude-haiku', 'claude-%'));
        
        $this->assertTrue($method->invoke($this->modelMapper, 'google-gemini-pro', '%gemini%'));
        $this->assertTrue($method->invoke($this->modelMapper, 'gemini', '%gemini%'));
        
        // Test non-matches
        $this->assertFalse($method->invoke($this->modelMapper, 'gpt-4', '%gpt-5%'));
        $this->assertFalse($method->invoke($this->modelMapper, 'openai-claude', 'claude-%'));
        $this->assertFalse($method->invoke($this->modelMapper, 'palm-model', '%gemini%'));
        
        // Test exact patterns (should return false as they're handled elsewhere)
        $this->assertFalse($method->invoke($this->modelMapper, 'exact-match', 'exact-match'));
    }

    /**
     * Test complex wildcard patterns
     */
    public function testComplexWildcardPatterns()
    {
        // Add more complex patterns to config
        $this->config->set('odin.llm.model_fixed_temperature.%test-%-model%', 0.6);
        
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        $result = $method->invoke($this->modelMapper, 'new-test-v1-model-pro');
        $this->assertEquals(0.6, $result);

        $result = $method->invoke($this->modelMapper, 'test-beta-model');
        $this->assertEquals(0.6, $result);
        
        // Should not match
        $result = $method->invoke($this->modelMapper, 'test-model');
        $this->assertNull($result);
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases()
    {
        $reflection = new ReflectionClass($this->modelMapper);
        $method = $reflection->getMethod('getFixedTemperatureForModel');
        $method->setAccessible(true);

        // Empty model name
        $result = $method->invoke($this->modelMapper, '');
        $this->assertNull($result);

        // Special characters in model name - create new mapper with updated config
        $specialConfig = new Config([
            'odin' => [
                'llm' => [
                    'default' => 'gpt-3.5-turbo',
                    'default_embedding' => 'text-embedding-ada-002',
                    'models' => [],
                    'model_fixed_temperature' => [
                        '%test.model%' => 0.3,
                    ]
                ]
            ]
        ]);
        
        $logger = new \Psr\Log\NullLogger();
        $specialMapper = new ModelMapper($specialConfig, $logger);
        
        $specialReflection = new ReflectionClass($specialMapper);
        $specialMethod = $specialReflection->getMethod('getFixedTemperatureForModel');
        $specialMethod->setAccessible(true);
        
        $result = $specialMethod->invoke($specialMapper, 'prefix-test.model-suffix');
        $this->assertEquals(0.3, $result);
    }
}

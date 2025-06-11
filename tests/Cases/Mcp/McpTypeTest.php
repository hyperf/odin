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

namespace HyperfTest\Odin\Cases\Mcp;

use Hyperf\Odin\Mcp\McpType;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Mcp\McpType
 */
class McpTypeTest extends AbstractTestCase
{
    public function testEnumValues()
    {
        // Test enum values
        $this->assertEquals('none', McpType::None->value);
        $this->assertEquals('stdio', McpType::Stdio->value);
        $this->assertEquals('http', McpType::Http->value);
    }

    public function testEnumCases()
    {
        // Test all cases exist
        $cases = McpType::cases();
        $this->assertCount(3, $cases);

        $values = array_map(fn ($case) => $case->value, $cases);
        $this->assertContains('none', $values);
        $this->assertContains('stdio', $values);
        $this->assertContains('http', $values);
    }

    public function testFromString()
    {
        // Test creating enum from string values
        $this->assertEquals(McpType::None, McpType::from('none'));
        $this->assertEquals(McpType::Stdio, McpType::from('stdio'));
        $this->assertEquals(McpType::Http, McpType::from('http'));
    }

    public function testTryFromString()
    {
        // Test creating enum from string values with tryFrom
        $this->assertEquals(McpType::None, McpType::tryFrom('none'));
        $this->assertEquals(McpType::Stdio, McpType::tryFrom('stdio'));
        $this->assertEquals(McpType::Http, McpType::tryFrom('http'));
        $this->assertNull(McpType::tryFrom('invalid'));
    }
}

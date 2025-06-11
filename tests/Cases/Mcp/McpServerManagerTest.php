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

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;
use ReflectionClass;

/**
 * @internal
 * @covers \Hyperf\Odin\Mcp\McpServerManager
 */
class McpServerManagerTest extends AbstractTestCase
{
    private string $stdioServerPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if container is available, if not, skip all tests
        if (! ApplicationContext::hasContainer()) {
            $this->markTestSkipped('ApplicationContext container not available - skipping MCP tests');
        }

        ClassLoader::init();
        ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

        $this->stdioServerPath = dirname(__DIR__, 3) . '/examples/mcp/stdio_server.php';

        // Check if stdio server file exists
        if (! file_exists($this->stdioServerPath)) {
            $this->markTestSkipped('STDIO server file not found: ' . $this->stdioServerPath);
        }
    }

    public function testConstructorWithValidConfigs()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'test-stdio-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $this->assertInstanceOf(McpServerManager::class, $manager);
    }

    public function testConstructorWithEmptyConfigs()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('McpServerManager requires at least one McpServerConfig.');

        new McpServerManager([]);
    }

    public function testConstructorWithInvalidConfig()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('McpServerManager expects an array of McpServerConfig instances.');

        new McpServerManager(['invalid-config']);
    }

    public function testDiscoverAndGetAllTools()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);

        // Test discover method
        $manager->discover();

        // Test getAllTools method
        $tools = $manager->getAllTools();
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        // Check that tools are ToolDefinition instances
        foreach ($tools as $tool) {
            $this->assertInstanceOf(ToolDefinition::class, $tool);
        }

        // Expected tools from stdio_server.php (echo, calculate)
        $toolNames = array_keys($tools);
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertContains('mcp_a_calculate', $toolNames);
    }

    public function testCallMcpToolEcho()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        // Test echo tool
        $result = $manager->callMcpTool('mcp_a_echo', ['message' => 'Hello, World!']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertNotEmpty($result['content']);

        // Check the echo result
        $content = $result['content'][0];
        $this->assertArrayHasKey('text', $content);
        $this->assertStringContainsString('Echo: Hello, World!', $content['text']);
    }

    public function testCallMcpToolCalculate()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        // Test calculate tool - addition
        $result = $manager->callMcpTool('mcp_a_calculate', [
            'operation' => 'add',
            'a' => 10,
            'b' => 5,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertNotEmpty($result['content']);

        // Parse the result to check calculation
        $content = $result['content'][0];
        $this->assertArrayHasKey('text', $content);
        $resultData = json_decode($content['text'], true);
        $this->assertIsArray($resultData);
        $this->assertEquals('add', $resultData['operation']);
        $this->assertEquals([10, 5], $resultData['operands']);
        $this->assertEquals(15, $resultData['result']);
    }

    public function testCallMcpToolWithInvalidToolName()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tool name format: invalid_tool_name');

        $manager->callMcpTool('invalid_tool_name', []);
    }

    public function testCallMcpToolWithInvalidSession()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid session : z');

        $manager->callMcpTool('mcp_z_nonexistent', []);
    }

    public function testMultipleServersWithDifferentLetters()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'first-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'second-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should have tools from both servers with different prefixes
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertContains('mcp_a_calculate', $toolNames);
        $this->assertContains('mcp_b_echo', $toolNames);
        $this->assertContains('mcp_b_calculate', $toolNames);

        // Test calling tools from different servers
        $result1 = $manager->callMcpTool('mcp_a_echo', ['message' => 'Server A']);
        $result2 = $manager->callMcpTool('mcp_b_echo', ['message' => 'Server B']);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    public function testSessionIndexToLetter()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);

        // Test the private sessionIndexToLetter method via reflection
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('sessionIndexToLetter');

        $this->assertEquals('a', $method->invoke($manager, 0));
        $this->assertEquals('b', $method->invoke($manager, 1));
        $this->assertEquals('c', $method->invoke($manager, 2));
        $this->assertEquals('z', $method->invoke($manager, 25));
        $this->assertEquals('ba', $method->invoke($manager, 26));
    }

    public function testDiscoverIdempotent()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'stdio-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);

        // Call discover multiple times
        $manager->discover();
        $tools1 = $manager->getAllTools();

        $manager->discover();
        $tools2 = $manager->getAllTools();

        // Should be the same
        $this->assertEquals(array_keys($tools1), array_keys($tools2));
    }

    public function testToolDescriptionContainsServerName()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'my-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $echoTool = $tools['mcp_a_echo'] ?? null;

        $this->assertNotNull($echoTool);
        $this->assertStringContainsString('MCP server [my-test-server]', $echoTool->getDescription());
    }

    public function testAllowedToolsFiltering()
    {
        // Test with only echo tool allowed
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'filtered-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: ['echo'] // Only allow echo tool
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should only have echo tool, not calculate tool
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertNotContains('mcp_a_calculate', $toolNames);
        $this->assertCount(1, $tools);
    }

    public function testAllowedToolsWithMultipleTools()
    {
        // Test with both tools allowed explicitly
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'multi-tool-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: ['echo', 'calculate'] // Allow both tools
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should have both tools
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertContains('mcp_a_calculate', $toolNames);
        $this->assertCount(2, $tools);
    }

    public function testAllowedToolsWithNonExistentTool()
    {
        // Test with a non-existent tool in allowed list
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'non-existent-tool-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: ['echo', 'nonexistent'] // Include non-existent tool
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should only have echo tool (nonexistent tool should be ignored)
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertNotContains('mcp_a_calculate', $toolNames);
        $this->assertNotContains('mcp_a_nonexistent', $toolNames);
        $this->assertCount(1, $tools);
    }

    public function testAllowedToolsWithEmptyList()
    {
        // Test with empty allowed tools list
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'empty-tools-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: [] // Empty list - no tools allowed
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();

        // Should have no tools
        $this->assertEmpty($tools);
    }

    public function testAllowedToolsNullMeansAllTools()
    {
        // Test with null allowed tools (should allow all)
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'all-tools-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: null // null means all tools allowed
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should have all available tools
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertContains('mcp_a_calculate', $toolNames);
        $this->assertCount(2, $tools);
    }

    public function testMultipleServersWithDifferentAllowedTools()
    {
        // Test multiple servers with different allowed tools
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'echo-only-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: ['echo'] // Only echo
            ),
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'calc-only-server',
                command: 'php',
                args: [$this->stdioServerPath],
                allowedTools: ['calculate'] // Only calculate
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();

        $tools = $manager->getAllTools();
        $toolNames = array_keys($tools);

        // Should have echo from first server and calculate from second server
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertNotContains('mcp_a_calculate', $toolNames);
        $this->assertNotContains('mcp_b_echo', $toolNames);
        $this->assertContains('mcp_b_calculate', $toolNames);
        $this->assertCount(2, $tools);
    }
}

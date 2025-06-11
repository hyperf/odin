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
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Psr\Log\LoggerInterface;
use Throwable;

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

        // Check if container is available and can safely create MCP components
        if (! ApplicationContext::hasContainer()) {
            $this->markTestSkipped('ApplicationContext container not available - skipping MCP tests');
        }

        $container = new Container((new DefinitionSourceFactory())());
        $container->set(LoggerInterface::class, new Logger());
        ApplicationContext::setContainer($container);

        $this->stdioServerPath = dirname(__DIR__, 3) . '/examples/mcp/stdio_server.php';

        // Check if stdio server file exists
        if (! file_exists($this->stdioServerPath)) {
            $this->markTestSkipped('STDIO server file not found: ' . $this->stdioServerPath);
        }

        // Try to create a test MCP config to see if it will work with the container
        try {
            $testConfig = new McpServerConfig(
                type: McpType::Stdio,
                name: 'test-probe',
                command: 'echo',
                args: ['test']
            );
            // Try to create McpServerManager - this will fail if Swoole classes are missing
            new McpServerManager([$testConfig]);
        } catch (Throwable $e) {
            // If we can't create McpServerManager due to missing Swoole classes, skip all tests
            if (str_contains($e->getMessage(), 'Swoole') || str_contains($e->getMessage(), 'Coroutine')) {
                $this->markTestSkipped('Swoole extension not available - skipping MCP tests: ' . $e->getMessage());
            }
            // Re-throw other types of exceptions
            throw $e;
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

    public function testBasicToolDiscovery()
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

        $tools = $manager->getAllTools();
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        // Check that tools are ToolDefinition instances
        foreach ($tools as $tool) {
            $this->assertInstanceOf(ToolDefinition::class, $tool);
        }

        // Expected basic tools
        $toolNames = array_keys($tools);
        $this->assertContains('mcp_a_echo', $toolNames);
        $this->assertContains('mcp_a_calculate', $toolNames);
    }

    public function testBasicToolCall()
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
        $result = $manager->callMcpTool('mcp_a_echo', ['message' => 'Test']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('Echo: Test', $result['content'][0]['text']);
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
}

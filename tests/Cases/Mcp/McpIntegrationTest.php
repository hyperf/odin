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
use Hyperf\Odin\Logger;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @internal
 * @covers \Hyperf\Odin\Mcp\McpServerConfig
 * @covers \Hyperf\Odin\Mcp\McpServerManager
 * @covers \Hyperf\Odin\Mcp\McpType
 */
class McpIntegrationTest extends AbstractTestCase
{
    private string $stdioServerPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if container is available and can safely create MCP components
        if (! ApplicationContext::hasContainer()) {
            $this->markTestSkipped('ApplicationContext container not available - skipping MCP tests');
        }

        ClassLoader::init();
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

    public function testBasicMcpWorkflow()
    {
        // Step 1: Create a single MCP server configuration
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        // Step 2: Initialize MCP server manager
        $manager = new McpServerManager($configs);

        // Step 3: Discover available tools
        $manager->discover();
        $tools = $manager->getAllTools();

        // Verify tool discovery
        $this->assertIsArray($tools);
        $this->assertGreaterThan(0, count($tools));

        // Expected basic tools
        $this->assertArrayHasKey('mcp_a_echo', $tools);
        $this->assertArrayHasKey('mcp_a_calculate', $tools);

        // Step 4: Test basic tool execution
        $echoResult = $manager->callMcpTool('mcp_a_echo', [
            'message' => 'Hello MCP',
        ]);
        $this->assertIsArray($echoResult);
        $this->assertArrayHasKey('content', $echoResult);
        $this->assertStringContainsString('Echo: Hello MCP', $echoResult['content'][0]['text']);

        // Test simple calculation
        $calcResult = $manager->callMcpTool('mcp_a_calculate', [
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);
        $this->assertIsArray($calcResult);
        $this->assertArrayHasKey('content', $calcResult);

        $resultData = json_decode($calcResult['content'][0]['text'], true);
        $this->assertEquals(8, $resultData['result']);
    }
}

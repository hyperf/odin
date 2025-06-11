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

use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpServerManager;
use Hyperf\Odin\Mcp\McpType;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use HyperfTest\Odin\Cases\AbstractTestCase;

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
        ClassLoader::init();
        ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
        parent::setUp();

        $this->stdioServerPath = dirname(__DIR__, 3) . '/examples/mcp/stdio_server.php';

        // Check if stdio server file exists
        if (! file_exists($this->stdioServerPath)) {
            $this->markTestSkipped('STDIO server file not found: ' . $this->stdioServerPath);
        }
    }

    public function testCompleteWorkflow()
    {
        // Step 1: Create MCP server configurations
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'calculation-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'echo-server',
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

        // Expected tools from both servers
        $expectedTools = [
            'mcp_a_echo',
            'mcp_a_calculate',
            'mcp_b_echo',
            'mcp_b_calculate',
        ];

        foreach ($expectedTools as $expectedTool) {
            $this->assertArrayHasKey($expectedTool, $tools);
            $this->assertInstanceOf(ToolDefinition::class, $tools[$expectedTool]);
        }

        // Step 4: Test tool execution scenarios

        // Test 1: Simple echo from first server
        $echoResult1 = $manager->callMcpTool('mcp_a_echo', [
            'message' => 'Hello from server A',
        ]);
        $this->assertIsArray($echoResult1);
        $this->assertArrayHasKey('content', $echoResult1);
        $this->assertStringContainsString('Echo: Hello from server A', $echoResult1['content'][0]['text']);

        // Test 2: Simple echo from second server
        $echoResult2 = $manager->callMcpTool('mcp_b_echo', [
            'message' => 'Hello from server B',
        ]);
        $this->assertIsArray($echoResult2);
        $this->assertArrayHasKey('content', $echoResult2);
        $this->assertStringContainsString('Echo: Hello from server B', $echoResult2['content'][0]['text']);

        // Test 3: Complex calculation operations
        $calculations = [
            ['operation' => 'add', 'a' => 15, 'b' => 25, 'expected' => 40],
            ['operation' => 'subtract', 'a' => 50, 'b' => 20, 'expected' => 30],
            ['operation' => 'multiply', 'a' => 6, 'b' => 7, 'expected' => 42],
            ['operation' => 'divide', 'a' => 100, 'b' => 4, 'expected' => 25],
        ];

        foreach ($calculations as $calc) {
            $calcResult = $manager->callMcpTool('mcp_a_calculate', [
                'operation' => $calc['operation'],
                'a' => $calc['a'],
                'b' => $calc['b'],
            ]);

            $this->assertIsArray($calcResult);
            $this->assertArrayHasKey('content', $calcResult);

            $resultData = json_decode($calcResult['content'][0]['text'], true);
            $this->assertIsArray($resultData);
            $this->assertEquals($calc['operation'], $resultData['operation']);
            $this->assertEquals([$calc['a'], $calc['b']], $resultData['operands']);
            $this->assertEquals($calc['expected'], $resultData['result']);
        }

        // Test 4: Cross-server calculation validation
        $sameCalculation = ['operation' => 'multiply', 'a' => 9, 'b' => 9];

        $resultA = $manager->callMcpTool('mcp_a_calculate', $sameCalculation);
        $resultB = $manager->callMcpTool('mcp_b_calculate', $sameCalculation);

        $dataA = json_decode($resultA['content'][0]['text'], true);
        $dataB = json_decode($resultB['content'][0]['text'], true);

        // Both servers should produce the same calculation result
        $this->assertEquals($dataA['result'], $dataB['result']);
        $this->assertEquals(81, $dataA['result']);
        $this->assertEquals(81, $dataB['result']);

        // Step 5: Verify tool metadata
        $echoToolA = $tools['mcp_a_echo'];
        $this->assertStringContainsString('calculation-server', $echoToolA->getDescription());
        $this->assertEquals('mcp_a_echo', $echoToolA->getName());

        $calcToolB = $tools['mcp_b_calculate'];
        $this->assertStringContainsString('echo-server', $calcToolB->getDescription());
        $this->assertEquals('mcp_b_calculate', $calcToolB->getName());

        // Step 6: Verify tool parameters
        $calcTool = $tools['mcp_a_calculate'];
        $parameters = $calcTool->getParameters();
        $this->assertNotNull($parameters);

        $paramArray = $parameters->toArray();
        $this->assertArrayHasKey('properties', $paramArray);
        $this->assertArrayHasKey('operation', $paramArray['properties']);
        $this->assertArrayHasKey('a', $paramArray['properties']);
        $this->assertArrayHasKey('b', $paramArray['properties']);
        $this->assertArrayHasKey('required', $paramArray);
        $this->assertContains('operation', $paramArray['required']);
        $this->assertContains('a', $paramArray['required']);
        $this->assertContains('b', $paramArray['required']);
    }

    public function testErrorHandling()
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
        $manager->discover();

        // Test division by zero
        try {
            $manager->callMcpTool('mcp_a_calculate', [
                'operation' => 'divide',
                'a' => 10,
                'b' => 0,
            ]);
            $this->fail('Expected exception for division by zero');
        } catch (Exception $e) {
            // The error message might be wrapped in a JSON-RPC error, just check for MCP error
            $this->assertStringContainsString('Failed to call MCP tool', $e->getMessage());
        }

        // Test invalid operation
        try {
            $manager->callMcpTool('mcp_a_calculate', [
                'operation' => 'invalid',
                'a' => 10,
                'b' => 5,
            ]);
            $this->fail('Expected exception for invalid operation');
        } catch (Exception $e) {
            // The error message might be wrapped in a JSON-RPC error, just check for MCP error
            $this->assertStringContainsString('Failed to call MCP tool', $e->getMessage());
        }
    }

    public function testToolHandlerFunctionality()
    {
        $configs = [
            new McpServerConfig(
                type: McpType::Stdio,
                name: 'handler-test-server',
                command: 'php',
                args: [$this->stdioServerPath]
            ),
        ];

        $manager = new McpServerManager($configs);
        $manager->discover();
        $tools = $manager->getAllTools();

        // Get the echo tool and test its handler directly
        $echoTool = $tools['mcp_a_echo'];
        $handler = $echoTool->getToolHandler();

        $this->assertIsCallable($handler);

        // Execute the handler directly
        $result = call_user_func($handler, ['message' => 'Direct handler test']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('Echo: Direct handler test', $result['content'][0]['text']);
    }
}

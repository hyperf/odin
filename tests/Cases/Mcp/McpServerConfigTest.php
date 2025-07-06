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

use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Mcp\McpServerConfig;
use Hyperf\Odin\Mcp\McpType;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Mcp\McpServerConfig
 */
class McpServerConfigTest extends AbstractTestCase
{
    public function testHttpServerConfigConstruction()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-http-server',
            url: 'https://api.example.com',
            token: 'test-token'
        );

        $this->assertEquals(McpType::Http, $config->getType());
        $this->assertEquals('test-http-server', $config->getName());
        $this->assertEquals('https://api.example.com', $config->getUrl());
        $this->assertEquals('test-token', $config->getAuthorizationToken());
        $this->assertEmpty($config->getCommand());
        $this->assertEmpty($config->getArgs());
        $this->assertNull($config->getAllowedTools());
        $this->assertEmpty($config->getHeaders());
    }

    public function testStdioServerConfigConstruction()
    {
        $config = new McpServerConfig(
            type: McpType::Stdio,
            name: 'test-stdio-server',
            command: 'php',
            args: ['/path/to/server.php', '--arg1', 'value1']
        );

        $this->assertEquals(McpType::Stdio, $config->getType());
        $this->assertEquals('test-stdio-server', $config->getName());
        $this->assertEquals('php', $config->getCommand());
        $this->assertEquals(['/path/to/server.php', '--arg1', 'value1'], $config->getArgs());
        $this->assertEmpty($config->getUrl());
        $this->assertNull($config->getAuthorizationToken());
        $this->assertEmpty($config->getHeaders());
    }

    public function testSetToken()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );

        $this->assertNull($config->getAuthorizationToken());

        $config->setToken('new-token');
        $this->assertEquals('new-token', $config->getAuthorizationToken());

        $config->setToken(null);
        $this->assertNull($config->getAuthorizationToken());
    }

    public function testSetHeaders()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );

        $this->assertEmpty($config->getHeaders());

        $newHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer test-token',
        ];

        $config->setHeaders($newHeaders);
        $this->assertEquals($newHeaders, $config->getHeaders());

        $config->setHeaders([]);
        $this->assertEmpty($config->getHeaders());
    }

    public function testSetHeadersUpdatesConnectConfig()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );

        // Initially headers should be empty
        $connectConfig = $config->getConnectConfig();
        $this->assertEmpty($connectConfig['headers']);

        // Set headers and verify connect config is updated
        $newHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $config->setHeaders($newHeaders);
        $connectConfig = $config->getConnectConfig();
        $this->assertEquals($newHeaders, $connectConfig['headers']);
    }

    public function testToArray()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            token: 'test-token',
            allowedTools: ['tool1', 'tool2']
        );

        $expected = [
            'type' => 'http',
            'name' => 'test-server',
            'url' => 'https://api.example.com',
            'token' => 'test-token',
            'command' => '',
            'args' => [],
            'allowedTools' => ['tool1', 'tool2'],
            'headers' => [],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testGetConnectTransportForHttp()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );

        $this->assertEquals('http', $config->getConnectTransport());
    }

    public function testGetConnectTransportForStdio()
    {
        $config = new McpServerConfig(
            type: McpType::Stdio,
            name: 'test-server',
            command: 'php',
            args: ['/path/to/server.php']
        );

        $this->assertEquals('stdio', $config->getConnectTransport());
    }

    public function testGetConnectTransportForUnsupportedType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MCP server type: none');

        $config = new McpServerConfig(
            type: McpType::None,
            name: 'test-server'
        );

        $config->getConnectTransport();
    }

    public function testGetConnectConfigForHttp()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            token: 'test-token'
        );

        $expected = [
            'base_url' => 'https://api.example.com',
            'auth' => [
                'type' => 'bearer',
                'token' => 'test-token',
            ],
            'headers' => [],
        ];

        $this->assertEquals($expected, $config->getConnectConfig());
    }

    public function testGetConnectConfigForHttpWithoutToken()
    {
        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );

        $expected = [
            'base_url' => 'https://api.example.com',
            'auth' => null,
            'headers' => [],
        ];

        $this->assertEquals($expected, $config->getConnectConfig());
    }

    public function testGetConnectConfigForStdio()
    {
        $config = new McpServerConfig(
            type: McpType::Stdio,
            name: 'test-server',
            command: 'php',
            args: ['/path/to/server.php', '--arg1']
        );

        $expected = [
            'command' => 'php',
            'args' => ['/path/to/server.php', '--arg1'],
        ];

        $this->assertEquals($expected, $config->getConnectConfig());
    }

    public function testValidationFailsForHttpWithoutUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP MCP server requires a URL.');

        new McpServerConfig(
            type: McpType::Http,
            name: 'test-server'
        );
    }

    public function testValidationFailsForStdioWithoutCommand()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('STDIO MCP server requires a command.');

        new McpServerConfig(
            type: McpType::Stdio,
            name: 'test-server'
        );
    }

    public function testValidationFailsForStdioWithoutArgs()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('STDIO MCP server requires arguments.');

        new McpServerConfig(
            type: McpType::Stdio,
            name: 'test-server',
            command: 'php'
        );
    }

    public function testValidationFailsForUnsupportedType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MCP server type: none');

        new McpServerConfig(
            type: McpType::None,
            name: 'test-server'
        );
    }

    public function testAllowedToolsHandling()
    {
        // Test with null allowed tools
        $config1 = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );
        $this->assertNull($config1->getAllowedTools());

        // Test with specific allowed tools
        $config2 = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            allowedTools: ['tool1', 'tool2', 'tool3']
        );
        $this->assertEquals(['tool1', 'tool2', 'tool3'], $config2->getAllowedTools());
    }

    public function testHeadersHandling()
    {
        // Test with empty headers (default)
        $config1 = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com'
        );
        $this->assertEmpty($config1->getHeaders());

        // Test with specific headers
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Test-Agent/1.0',
        ];
        $config2 = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            headers: $headers
        );
        $this->assertEquals($headers, $config2->getHeaders());
    }

    public function testGetConnectConfigForHttpWithHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            token: 'test-token',
            headers: $headers
        );

        $expected = [
            'base_url' => 'https://api.example.com',
            'auth' => [
                'type' => 'bearer',
                'token' => 'test-token',
            ],
            'headers' => $headers,
        ];

        $this->assertEquals($expected, $config->getConnectConfig());
    }

    public function testToArrayWithHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $config = new McpServerConfig(
            type: McpType::Http,
            name: 'test-server',
            url: 'https://api.example.com',
            token: 'test-token',
            allowedTools: ['tool1', 'tool2'],
            headers: $headers
        );

        $expected = [
            'type' => 'http',
            'name' => 'test-server',
            'url' => 'https://api.example.com',
            'token' => 'test-token',
            'command' => '',
            'args' => [],
            'allowedTools' => ['tool1', 'tool2'],
            'headers' => $headers,
        ];

        $this->assertEquals($expected, $config->toArray());
    }
}

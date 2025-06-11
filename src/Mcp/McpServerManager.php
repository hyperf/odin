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

namespace Hyperf\Odin\Mcp;

use Dtyq\PhpMcp\Client\McpClient;
use Dtyq\PhpMcp\Client\Session\ClientSession;
use Dtyq\PhpMcp\Shared\Kernel\Application;
use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Contract\Mcp\McpServerConfigInterface;
use Hyperf\Odin\Contract\Mcp\McpServerManagerInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Exception\McpException;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use Throwable;

class McpServerManager implements McpServerManagerInterface
{
    /**
     * @var array<string, McpServerConfigInterface>
     */
    protected array $mcpServerConfigs;

    /**
     * @var array<int, ClientSession>
     */
    private array $sessions = [];

    /**
     * @var array<string, ToolDefinition>
     */
    private array $tools = [];

    /**
     * @var array<string, int> Mapping from letter to session index
     */
    private array $sessionLetterToIndex = [];

    private McpClient $mcpClient;

    private bool $discovered = false;

    /**
     * @param array<McpServerConfigInterface> $mcpServerConfigs
     * @throws InvalidArgumentException
     */
    public function __construct(array $mcpServerConfigs)
    {
        foreach ($mcpServerConfigs as $mcpServerConfig) {
            if (! $mcpServerConfig instanceof McpServerConfig) {
                throw new InvalidArgumentException('McpServerManager expects an array of McpServerConfig instances.');
            }
            $this->mcpServerConfigs[$mcpServerConfig->getName()] = $mcpServerConfig;
        }
        if (empty($this->mcpServerConfigs)) {
            throw new InvalidArgumentException('McpServerManager requires at least one McpServerConfig.');
        }
        $this->mcpClient = new McpClient('Odin', '1.0.0', new Application(ApplicationContext::getContainer()));
    }

    public function __destruct()
    {
        $this->mcpClient->close();
    }

    public function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $registered = [];
        $sessionIndex = 0;
        foreach ($this->mcpServerConfigs as $mcpServerConfig) {
            try {
                if (in_array($mcpServerConfig->getName(), $registered, true)) {
                    continue; // Skip if already registered
                }
                $session = $this->mcpClient->connect($mcpServerConfig->getConnectTransport(), $mcpServerConfig->getConnectConfig());
                $session->initialize();

                $this->sessions[$sessionIndex] = $session;

                $this->toolRegister($mcpServerConfig, $session, $sessionIndex);

                $registered[] = $mcpServerConfig->getName();
                ++$sessionIndex;
            } catch (Throwable $throwable) {
                throw new McpException(sprintf(
                    'Failed to connect to MCP server "%s" : %s',
                    $mcpServerConfig->getName(),
                    $throwable->getMessage()
                ), 0, $throwable);
            }
        }

        $this->discovered = true;
    }

    /**
     * @return array<ToolDefinition>
     */
    public function getAllTools(): array
    {
        $this->discover();
        return $this->tools;
    }

    public function callMcpTool(string $toolName, array $args = []): array
    {
        $this->discover();

        // Parse tool name to extract MCP server letter and original tool name
        if (! preg_match('/^mcp_([a-z]+)_(.+)$/', $toolName, $matches)) {
            throw new InvalidArgumentException("Invalid tool name format: {$toolName}");
        }

        $sessionLetter = $matches[1];
        $originalToolName = $matches[2];

        $sessionIndex = $this->sessionLetterToIndex[$sessionLetter] ?? null;
        $session = $this->sessions[$sessionIndex] ?? null;

        if (is_null($sessionIndex) || is_null($session)) {
            throw new InvalidArgumentException("Invalid session : {$sessionLetter}");
        }

        try {
            $result = $session->callTool($originalToolName, $args);
            return $result->toArray();
        } catch (Throwable $throwable) {
            throw new McpException(sprintf(
                'Failed to call MCP tool "%s" on server "%s": %s',
                $originalToolName,
                $sessionLetter,
                $throwable->getMessage()
            ), 0, $throwable);
        }
    }

    protected function toolRegister(McpServerConfig $mcpServerConfig, ClientSession $clientSession, int $sessionIndex): void
    {
        $sessionLetter = $this->sessionIndexToLetter($sessionIndex);
        $this->sessionLetterToIndex[$sessionLetter] = $sessionIndex;

        $namePrefix = "mcp_{$sessionLetter}_";
        $descriptionPrefix = "MCP server [{$mcpServerConfig->getName()}] - ";
        $allowedTools = $mcpServerConfig->getAllowedTools();

        $result = $clientSession->listTools();
        foreach ($result->getTools() as $mcpTool) {
            $originalToolName = $mcpTool->getName();
            
            // Check if tool is allowed
            if ($allowedTools !== null && ! in_array($originalToolName, $allowedTools, true)) {
                continue; // Skip this tool if it's not in the allowed list
            }
            
            $name = $namePrefix . $originalToolName;
            $tool = new ToolDefinition(
                name: $name,
                description: $descriptionPrefix . $mcpTool->getDescription(),
                parameters: ToolParameters::fromArray($mcpTool->getInputSchema()),
                toolHandler: function (array $args) use ($name) {
                    return $this->callMcpTool($name, $args);
                }
            );
            $this->tools[$tool->getName()] = $tool;
        }
    }

    /**
     * Convert session index to letter representation (0->a, 1->b, 2->c, etc.).
     */
    private function sessionIndexToLetter(int $index): string
    {
        $letters = '';
        do {
            $letters = chr(97 + ($index % 26)) . $letters;
            $index = intval($index / 26);
        } while ($index > 0);

        return $letters;
    }
}

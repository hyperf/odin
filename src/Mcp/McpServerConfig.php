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

use Hyperf\Odin\Contract\Mcp\McpServerConfigInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;

class McpServerConfig implements McpServerConfigInterface
{
    public function __construct(
        protected McpType $type,
        protected string $name,
        protected string $url = '',
        protected ?string $token = null,
        protected string $command = '',
        protected array $args = [],
        protected ?array $allowedTools = null,
        protected array $headers = [],
    ) {
        $this->validate();
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function getType(): McpType
    {
        return $this->type;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAuthorizationToken(): ?string
    {
        return $this->token;
    }

    public function getAllowedTools(): ?array
    {
        return $this->allowedTools;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
            'url' => $this->url,
            'token' => $this->token,
            'command' => $this->command,
            'args' => $this->args,
            'allowedTools' => $this->allowedTools,
            'headers' => $this->headers,
        ];
    }

    public function getConnectTransport(): string
    {
        return match ($this->type) {
            McpType::Http => 'http',
            McpType::Stdio => 'stdio',
            default => throw new InvalidArgumentException('Unsupported MCP server type: ' . $this->type->value),
        };
    }

    public function getConnectConfig(): array
    {
        return match ($this->type) {
            McpType::Http => [
                'base_url' => $this->url,
                'auth' => $this->getAuthConfig(),
                'headers' => $this->headers,
            ],
            McpType::Stdio => [
                'command' => $this->command,
                'args' => $this->args,
            ],
            default => throw new InvalidArgumentException('Unsupported MCP server type: ' . $this->type->value),
        };
    }

    private function getAuthConfig(): ?array
    {
        if (! empty($this->token)) {
            return [
                'type' => 'bearer',
                'token' => $this->token,
            ];
        }
        return null;
    }

    private function validate(): void
    {
        switch ($this->type) {
            case McpType::Http:
                if (empty($this->url)) {
                    throw new InvalidArgumentException('HTTP MCP server requires a URL.');
                }
                break;
            case McpType::Stdio:
                if (empty($this->command)) {
                    throw new InvalidArgumentException('STDIO MCP server requires a command.');
                }
                if (empty($this->args)) {
                    throw new InvalidArgumentException('STDIO MCP server requires arguments.');
                }
                break;
            default:
                throw new InvalidArgumentException('Unsupported MCP server type: ' . $this->type->value);
        }
    }
}

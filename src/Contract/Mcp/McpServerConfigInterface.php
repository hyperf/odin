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

namespace Hyperf\Odin\Contract\Mcp;

use Hyperf\Odin\Mcp\McpType;

interface McpServerConfigInterface
{
    public function getType(): McpType;

    public function getName(): string;

    public function getAuthorizationToken(): ?string;

    public function getUrl(): string;

    public function getCommand(): string;

    public function getArgs(): array;

    public function getAllowedTools(): ?array;

    public function getHeaders(): array;

    public function getEnv(): array;

    public function toArray(): array;

    public function getConnectTransport(): string;

    public function getConnectConfig(): array;

    public function setToken(?string $token): void;

    public function setHeaders(array $headers): void;
}

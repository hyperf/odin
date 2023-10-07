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

namespace Hyperf\Odin\Message;

interface MessageInterface
{
    public function getRole(): Role;

    public function getContent(): string;

    public function setRole(Role $role): static;

    public function setContent(string $content): static;

    public function toArray(): array;
}

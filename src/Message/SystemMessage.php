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

/**
 * 系统消息类.
 *
 * 用于表示系统级别的指令或消息
 */
class SystemMessage extends AbstractMessage
{
    /**
     * 角色固定为系统
     */
    protected Role $role = Role::System;

    /**
     * 从数组创建消息实例.
     *
     * @param array $message 消息数组
     * @return static 消息实例
     */
    public static function fromArray(array $message): self
    {
        $content = $message['content'] ?? '';

        return new self($content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }
}

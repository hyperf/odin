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
 * 用户消息类.
 *
 * 用于表示用户发送的消息，支持文本和多媒体内容
 */
class UserMessage extends AbstractMessage
{
    /**
     * @var null|UserMessageContent[]
     */
    protected ?array $contents = null;

    /**
     * 角色固定为用户.
     */
    protected Role $role = Role::User;

    /**
     * 构造函数.
     *
     * @param string $content 消息内容
     * @param array $context 上下文变量
     */
    public function __construct(string $content = '', array $context = [])
    {
        parent::__construct($content, $context);
    }

    /**
     * 添加内容项.
     *
     * @param UserMessageContent $content 用户消息内容项
     * @return self 支持链式调用
     */
    public function addContent(UserMessageContent $content): self
    {
        if ($this->contents === null) {
            $this->contents = [];
        }
        $this->contents[] = $content;
        return $this;
    }

    /**
     * 转换为数组.
     *
     * @return array 消息数组表示
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role->value,
            'content' => $this->content,
            'context' => $this->context,
        ];
        if (! is_null($this->contents)) {
            $contents = [];
            foreach ($this->contents as $content) {
                $contents[] = $content->toArray();
            }
            $data['content'] = $contents;
        }
        return $data;
    }

    /**
     * 获取内容项列表.
     *
     * @return null|UserMessageContent[] 内容项列表
     */
    public function getContents(): ?array
    {
        return $this->contents;
    }

    public function setContents(?array $contents): void
    {
        $this->contents = $contents;
    }

    /**
     * 获取消息内容.
     *
     * @return string 消息内容文本
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * 从数组创建消息实例.
     *
     * @param array $message 消息数组
     * @return static 消息实例
     */
    public static function fromArray(array $message): self
    {
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            $instance = new self($content);
        } elseif (is_array($content)) {
            $instance = new self('');
            foreach ($content as $item) {
                $userMessageContent = (new UserMessageContent($item['type'] ?? ''))
                    ->setText($item['text'] ?? '')
                    ->setImageUrl($item['image_url']['url'] ?? '');
                if ($userMessageContent->isValid()) {
                    $instance->addContent($userMessageContent);
                }
            }
        } else {
            $instance = new self('');
        }

        return $instance;
    }

    public function hasImageMultiModal(): bool
    {
        if (empty($this->contents)) {
            return false;
        }
        foreach ($this->contents as $content) {
            if ($content->getType() === UserMessageContent::IMAGE_URL) {
                return true;
            }
        }
        return false;
    }
}

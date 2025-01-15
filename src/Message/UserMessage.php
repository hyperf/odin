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

class UserMessage extends AbstractMessage
{
    /**
     * @var null|UserMessageContent[]
     */
    protected ?array $contents = null;

    protected Role $role = Role::User;

    public function __construct(string $content = '', array $context = [])
    {
        parent::__construct($content, $context);
    }

    public function addContent(UserMessageContent $content): self
    {
        $this->contents[] = $content;
        return $this;
    }

    public function toArray(): array
    {
        if (! is_null($this->contents)) {
            $contents = [];
            foreach ($this->contents as $content) {
                $contents[] = $content->toArray();
            }
            return [
                'role' => $this->role->value,
                'content' => $contents,
            ];
        }
        return parent::toArray();
    }

    public static function fromArray(array $message): static
    {
        $content = $message['content'] ?? '';
        if (is_string($content)) {
            return new static($content);
        }
        if (is_array($content)) {
            $userMessage = new static('');
            foreach ($content as $item) {
                $userMessageContent = (new UserMessageContent($item['type'] ?? ''))
                    ->setText($item['text'] ?? '')
                    ->setImageUrl($item['image_url']['url'] ?? '');
                if ($userMessageContent->isValid()) {
                    $userMessage->addContent($userMessageContent);
                }
            }
            return $userMessage;
        }
        return new static('');
    }
}

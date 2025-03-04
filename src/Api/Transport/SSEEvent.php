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

namespace Hyperf\Odin\Api\Transport;

use JsonSerializable;

/**
 * SSE 事件封装类.
 */
class SSEEvent implements JsonSerializable
{
    /**
     * 事件类型.
     */
    private string $event;

    /**
     * 事件数据.
     */
    private mixed $data;

    /**
     * 事件 ID.
     */
    private ?string $id;

    /**
     * 重连等待时间（毫秒）.
     */
    private ?int $retry;

    /**
     * 创建一个新的 SSE 事件.
     */
    public function __construct(
        mixed $data = '',
        string $event = 'message',
        ?string $id = null,
        ?int $retry = null
    ) {
        $this->data = $data;
        $this->event = $event;
        $this->id = $id;
        $this->retry = $retry;
    }

    /**
     * 从数组创建 SSE 事件.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['data'] ?? '',
            $data['event'] ?? 'message',
            $data['id'] ?? null,
            isset($data['retry']) ? (int) $data['retry'] : null
        );
    }

    /**
     * 获取事件类型.
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * 设置事件类型.
     */
    public function setEvent(string $event): self
    {
        $this->event = $event;
        return $this;
    }

    /**
     * 获取事件数据.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * 设置事件数据.
     */
    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * 获取事件 ID.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * 设置事件 ID.
     */
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * 获取重连等待时间.
     */
    public function getRetry(): ?int
    {
        return $this->retry;
    }

    /**
     * 设置重连等待时间.
     */
    public function setRetry(?int $retry): self
    {
        $this->retry = $retry;
        return $this;
    }

    /**
     * 转换为数组.
     */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'id' => $this->id,
            'retry' => $this->retry,
        ];
    }

    /**
     * 检查事件是否为空.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * 实现 JsonSerializable 接口.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 格式化为 SSE 文本格式.
     */
    public function format(): string
    {
        $result = '';

        if ($this->event !== 'message') {
            $result .= "event: {$this->event}\n";
        }

        // 处理多行数据
        $data = $this->data;
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        if (is_string($data)) {
            // 处理多行数据，每行前面加上 "data: "
            $dataLines = explode("\n", $data);
            foreach ($dataLines as $line) {
                $result .= "data: {$line}\n";
            }
        } else {
            $result .= "data: \n";
        }

        if ($this->id !== null) {
            $result .= "id: {$this->id}\n";
        }

        if ($this->retry !== null) {
            $result .= "retry: {$this->retry}\n";
        }

        return $result . "\n";
    }
}

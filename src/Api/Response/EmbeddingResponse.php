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

namespace Hyperf\Odin\Api\Response;

class EmbeddingResponse extends AbstractResponse
{
    /**
     * 响应对象类型.
     */
    protected string $object = 'list';

    /**
     * 嵌入数据.
     *
     * @var Embedding[]
     */
    protected array $data = [];

    /**
     * 模型名称.
     */
    protected ?string $model = null;

    /**
     * 获取响应对象类型.
     */
    public function getObject(): string
    {
        return $this->object;
    }

    /**
     * 设置响应对象类型.
     */
    public function setObject(string $object): self
    {
        $this->object = $object;
        return $this;
    }

    /**
     * 获取嵌入数据.
     *
     * @return Embedding[]
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 设置嵌入数据.
     *
     * @param array $data 嵌入数据数组
     */
    public function setData(array $data): self
    {
        $parsedData = [];
        foreach ($data as $item) {
            if (isset($item['object']) && $item['object'] === 'embedding') {
                $parsedData[] = Embedding::fromArray($item);
            }
        }
        $this->data = $parsedData;
        return $this;
    }

    /**
     * 获取模型名称.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * 设置模型名称.
     */
    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'object' => $this->object,
            'data' => array_map(fn (Embedding $embedding) => $embedding->toArray(), $this->data),
            'model' => $this->model,
            'usage' => [
                'prompt_tokens' => 0,
                'total_tokens' => 0,
            ],
        ];
        if ($this->usage) {
            $data['usage']['prompt_tokens'] = $this->usage->getPromptTokens();
            $data['usage']['total_tokens'] = $this->usage->getTotalTokens();
        }

        return $data;
    }

    /**
     * 解析响应内容.
     */
    protected function parseContent(): self
    {
        $content = json_decode($this->originResponse->getBody()->getContents(), true);

        if (isset($content['object'])) {
            $this->setObject($content['object']);
        }

        if (isset($content['data'])) {
            $this->setData($content['data']);
        }

        if (isset($content['model'])) {
            $this->setModel($content['model']);
        }

        if (isset($content['usage'])) {
            $this->setUsage(Usage::fromArray($content['usage']));
        }

        return $this;
    }
}

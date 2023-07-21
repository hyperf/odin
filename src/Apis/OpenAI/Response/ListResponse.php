<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


class ListResponse extends AbstractResponse
{

    protected array $data = [];
    protected ?string $model;
    protected ?Usage $usage;

    protected function parseContent(): static
    {
        $content = json_decode($this->content, true);
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

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $parsedData = [];
        foreach ($data as $item) {
            if (isset($item['object'])) {
                switch ($item['object']) {
                    case 'model':
                        $parsedData[] = Model::fromArray($item);
                        break;
                    case 'embedding':
                        $parsedData[] = Embedding::fromArray($item);
                        break;
                }
            }
        }
        $this->data = $parsedData;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function setUsage(?Usage $usage): static
    {
        $this->usage = $usage;
        return $this;
    }

}
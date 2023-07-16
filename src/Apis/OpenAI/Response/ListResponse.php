<?php

namespace Hyperf\Odin\Apis\OpenAI\Response;


use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ListResponse extends AbstractResponse
{

    protected array $data = [];

    protected function parseContent(): static
    {
        $content = json_decode($this->content, true);
        if (isset($content['data'])) {
            $this->setData($content['data']);
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
                }
            }
        }
        $this->data = $parsedData;
        return $this;
    }


}
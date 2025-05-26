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

use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Utils\TokenEstimator;
use Stringable;

class ChatCompletionResponse extends AbstractResponse implements Stringable
{
    protected ?string $id = null;

    protected ?string $object = null;

    protected ?int $created = null;

    protected ?string $model = null;

    /**
     * @var null|ChatCompletionChoice[]
     */
    protected ?array $choices = [];

    private ?int $totalTokenEstimate = null;

    public function __toString(): string
    {
        return trim($this->getChoices()[0]?->getMessage()?->getContent() ?: '');
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(?string $object): self
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(null|int|string $created): self
    {
        $this->created = (int) $created;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getFirstChoice(): ?ChatCompletionChoice
    {
        return $this->choices[0] ?? null;
    }

    /**
     * @return null|ChatCompletionChoice[]
     */
    public function getChoices(): ?array
    {
        return $this->choices;
    }

    public function setChoices(?array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    public function calculateTokenEstimates(): int
    {
        if ($this->totalTokenEstimate) {
            return $this->totalTokenEstimate;
        }
        $estimator = new TokenEstimator($this->model);

        $this->totalTokenEstimate = $estimator->estimateTokens($this->getFirstChoice()?->getMessage()?->getContent() ?? '');

        return $this->totalTokenEstimate;
    }

    protected function parseContent(): self
    {
        $this->content = $this->originResponse->getBody()->getContents();
        $content = json_decode($this->content, true);

        // 有一些服务商是在返回值中提示错误，暂定没有choices字段的时候，就是错误
        if (! isset($content['choices'])) {
            throw new LLMApiException('No choices found in response, please check the response content: ' . $this->content);
        }

        if (isset($content['id'])) {
            $this->setId($content['id']);
        }
        if (isset($content['object'])) {
            $this->setObject($content['object']);
        }
        if (isset($content['created'])) {
            $this->setCreated($content['created']);
        }
        if (isset($content['model'])) {
            $this->setModel($content['model']);
        }
        $this->setChoices($this->buildChoices($content['choices']));
        if (isset($content['usage'])) {
            $this->setUsage(Usage::fromArray($content['usage']));
        }
        return $this;
    }

    protected function buildChoices(mixed $choices): array
    {
        $result = [];
        foreach ($choices as $choice) {
            $result[] = ChatCompletionChoice::fromArray($choice);
        }
        return $result;
    }
}

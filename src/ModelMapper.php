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

namespace Hyperf\Odin;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Odin\Model\EmbeddingInterface;
use Hyperf\Odin\Model\ModelInterface;
use InvalidArgumentException;

class ModelMapper
{
    protected string $defaultModel = '';

    protected string $defaultEmbeddingModel = '';

    protected array $models = [];

    public function __construct(protected ConfigInterface $config)
    {
        $this->defaultModel = $config->get('odin.llm.default', 'gpt-3.5-turbo');
        $this->defaultEmbeddingModel = $config->get('odin.llm.default_embedding', 'text-embedding-ada-002');
        $models = $config->get('odin.llm.models', []);
        foreach ($models as $model => $item) {
            if (! $model || ! isset($item['implementation'])) {
                continue;
            }
            $implementation = $item['implementation'];
            $modelObject = new $implementation($model, $item['config'] ?? []);
            if (isset($item['type']) && $item['type'] === 'embedding') {
                if (! $modelObject instanceof EmbeddingInterface) {
                    throw new InvalidArgumentException(sprintf('Model %s must be an instance of %s.', $model, EmbeddingInterface::class));
                }
                $this->models['embedding'][$model] = $modelObject;
            } else {
                if (! $modelObject instanceof ModelInterface) {
                    throw new InvalidArgumentException(sprintf('Model %s must be an instance of %s.', $model, ModelInterface::class));
                }
                $this->models['chat'][$model] = $modelObject;
            }
        }
    }

    public function getDefaultModel(): ModelInterface
    {
        return $this->getModel($this->defaultModel);
    }

    public function getDefaultEmbeddingModel(): EmbeddingInterface
    {
        return $this->getEmbeddingModel($this->defaultEmbeddingModel);
    }

    /**
     * Alias for getChatModel(string $model) method.
     */
    public function getModel(string $model): ModelInterface
    {
        return $this->getChatModel($model);
    }

    public function getChatModel(string $model): ModelInterface
    {
        if ($model === '') {
            $model = $this->defaultModel;
        }
        if (! isset($this->models['chat'][$model])) {
            throw new InvalidArgumentException(sprintf('Chat Model %s is not defined.', $model));
        }
        return $this->models['chat'][$model];
    }

    public function getEmbeddingModel(string $model): EmbeddingInterface
    {
        if ($model === '') {
            $model = $this->defaultEmbeddingModel;
        }
        if (! isset($this->models['embedding'][$model])) {
            throw new InvalidArgumentException(sprintf('Embedding Model %s is not defined.', $model));
        }
        return $this->models['embedding'][$model];
    }

    public function getModels(string $type = ''): array
    {
        if ($type === 'embedding') {
            return $this->models['embedding'];
        }
        if ($type === 'chat') {
            return $this->models['chat'];
        }
        return $this->models;
    }
}

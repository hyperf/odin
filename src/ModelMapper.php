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
use Hyperf\Odin\Model\ModelInterface;
use InvalidArgumentException;

class ModelMapper
{
    protected string $defaultModel = '';
    protected array $models = [];

    public function __construct(protected ConfigInterface $config)
    {
        $this->defaultModel = $config->get('odin.llm.default', 'gpt-3.5-turbo');
        $models = $config->get('odin.llm.models', []);
        foreach ($models as $model => $item) {
            if (! $model || ! isset($item['implementation'])) {
                continue;
            }
            $implementation = $item['implementation'];
            $modelObject = new $implementation($model, $item['config'] ?? []);
            if (! $modelObject instanceof ModelInterface) {
                throw new InvalidArgumentException(sprintf('Model %s must be an instance of %s.', $model, ModelInterface::class));
            }
            $this->models[$model] = $modelObject;
        }
    }

    public function getDefaultModel(): ModelInterface
    {
        return $this->getModel($this->defaultModel);
    }

    public function getModel(string $model): ModelInterface
    {
        if ($model === '') {
            $model = $this->defaultModel;
        }
        if (! isset($this->models[$model])) {
            throw new InvalidArgumentException(sprintf('Model %s is not defined.', $model));
        }
        return $this->models[$model];
    }

    public function getModels(): array
    {
        return $this->models;
    }
}

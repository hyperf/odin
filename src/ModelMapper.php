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
            if (! isset($item['name'], $item['api_type'])) {
                continue;
            }
            $this->models[$model] = new Model($item['name'], $item['api_type']);
        }
    }

    public function getModel(string $model): Model
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

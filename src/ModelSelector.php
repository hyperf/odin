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

use RuntimeException;
use Yethee\Tiktoken\EncoderProvider;

class ModelSelector
{
    public function __construct(protected EncoderProvider $encoderProvider) {}

    public function select(string $text, array $availableModels = []): Model
    {
        // Filter $availableModels to only include Model object
        $availableModels = array_filter($availableModels, fn ($model) => $model instanceof Model);
        $modelNames = array_map(fn (Model $model) => $model->getName(), $availableModels);
        $encoder = $this->encoderProvider->getForModel('gpt-3.5-turbo');
        $tokens = $encoder->encode($text);
        $totalTokens = count($tokens);
        /**
         * Detect a suitable model from $availableModels
         * if $totalTokens is less than 4k and when gpt-4 not in $availableModels , use gpt-3.5-turbo
         * if $totalTokens is less than 4k and when gpt-4 in $availableModels , use gpt-4
         * if $totalTokens is less than 16k and when gpt-4-32k not in $availableModels , use gpt-3.5-turbo-16k
         * if $totalTokens is less than 16k and when gpt-4-32k in $availableModels , use gpt-4-32k.
         * if $totalTokens is more than 16k and less than 32k, use gpt-4-32k, otherwise throw a no suitable model exception.
         */
        if ($totalTokens < 4000) {
            if (! in_array('gpt-4', $modelNames)) {
                return $this->getModelByName('gpt-3.5-turbo', $availableModels);
            }
            return $this->getModelByName('gpt-4', $availableModels);
        }
        if ($totalTokens < 16000) {
            if (! in_array('gpt-4-32k', $modelNames)) {
                return $this->getModelByName('gpt-3.5-turbo-16k', $availableModels);
            }
            return $this->getModelByName('gpt-4-32k', $availableModels);
        }
        if ($totalTokens < 32000) {
            return $this->getModelByName('gpt-4-32k', $availableModels);
        }
        throw new RuntimeException('No suitable model found.');
    }

    protected function getModelByName(string $name, array $models = []): ?Model
    {
        // Filter $models to only include Model object
        $models = array_filter($models, fn ($model) => $model instanceof Model);
        foreach ($models as $model) {
            if ($model->getName() === $name) {
                return $model;
            }
        }
        return null;
    }
}

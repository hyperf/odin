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
namespace Hyperf\Odin\VectorStore\Qdrant\Collection;

class Config
{
    protected mixed $params;

    protected mixed $hnswConfig;

    protected mixed $optimizerConfig;

    protected mixed $walConfig;

    protected mixed $quantizationConfig;

    public function __construct(array $config)
    {
        # TODO: 结构体
        $this->params = $config['params'];
        $this->hnswConfig = $config['hnsw_config'];
        $this->optimizerConfig = $config['optimizer_config'];
        $this->walConfig = $config['wal_config'];
        $this->quantizationConfig = $config['quantization_config'];
    }
}

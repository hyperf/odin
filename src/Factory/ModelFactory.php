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

namespace Hyperf\Odin\Factory;

use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Contract\Model\EmbeddingInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Model\AbstractModel;
use Hyperf\Odin\Model\ModelOptions;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 模型工厂类，负责创建LLM模型实例.
 */
class ModelFactory
{
    /**
     * 创建模型实例.
     *
     * @param string $implementation 模型实现类
     * @param string $modelName 模型名称/端点
     * @param array $config 模型配置
     * @param null|ModelOptions $modelOptions 模型选项
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function create(
        string $implementation,
        string $modelName,
        array $config = [],
        ?ModelOptions $modelOptions = null,
        ?ApiOptions $apiOptions = null,
        ?LoggerInterface $logger = null
    ): EmbeddingInterface|ModelInterface {
        // 验证实现类是否存在
        if (! class_exists($implementation)) {
            throw new InvalidArgumentException(sprintf('Implementation %s is not defined.', $implementation));
        }

        // 创建模型实例
        $model = new $implementation($modelName, $config, $logger);
        if ($model instanceof AbstractModel) {
            $modelOptions && $model->setModelOptions($modelOptions);
            $apiOptions && $model->setApiRequestOptions($apiOptions);
        }

        // 验证模型实例类型
        $isValidModel = $model instanceof ModelInterface || $model instanceof EmbeddingInterface;
        if (! $isValidModel) {
            throw new InvalidArgumentException(
                sprintf('Implementation %s does not implement ModelInterface or EmbeddingInterface.', $implementation)
            );
        }

        return $model;
    }
}

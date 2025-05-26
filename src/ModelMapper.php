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
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Constants\ModelType;
use Hyperf\Odin\Contract\Model\EmbeddingInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Model\ModelOptions;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 模型映射器，负责自动加载 odin 配置文件的模型实例.
 */
class ModelMapper
{
    /**
     * 默认模型名称.
     */
    protected string $defaultModel = '';

    /**
     * 默认嵌入模型名称.
     */
    protected string $defaultEmbeddingModel = '';

    /**
     * 模型实例缓存.
     */
    protected array $models = [];

    /**
     * 构造函数.
     */
    public function __construct(protected ConfigInterface $config, protected LoggerInterface $logger)
    {
        $this->defaultModel = $config->get('odin.llm.default', 'gpt-3.5-turbo');
        $this->defaultEmbeddingModel = $config->get('odin.llm.default_embedding', 'text-embedding-ada-002');

        // 初始化时加载配置的模型
        $models = $config->get('odin.llm.models', []);
        foreach ($models as $model => $item) {
            $this->addModel($model, $item);
        }
    }

    /**
     * 获取默认模型.
     */
    public function getDefaultModel(): ModelInterface
    {
        return $this->getModel($this->defaultModel);
    }

    /**
     * 获取默认嵌入模型.
     */
    public function getDefaultEmbeddingModel(): EmbeddingInterface
    {
        return $this->getEmbeddingModel($this->defaultEmbeddingModel);
    }

    /**
     * 获取模型实例（别名方法）.
     */
    public function getModel(string $model): ModelInterface
    {
        return $this->getChatModel($model);
    }

    /**
     * 获取聊天模型实例.
     */
    public function getChatModel(string $model): ModelInterface
    {
        if ($model === '') {
            $model = $this->defaultModel;
        }

        // 检查缓存
        if (isset($this->models[ModelType::CHAT][$model])) {
            return $this->models[ModelType::CHAT][$model];
        }

        // 如果模型未缓存，创建模型
        $modelConfig = $this->config->get('odin.llm.models.' . $model);
        if (empty($modelConfig)) {
            throw new InvalidArgumentException(sprintf('Chat Model %s is not defined.', $model));
        }

        $this->addModel($model, $modelConfig);

        if (! isset($this->models[ModelType::CHAT][$model])) {
            throw new InvalidArgumentException(sprintf('Failed to create Chat Model %s.', $model));
        }

        return $this->models[ModelType::CHAT][$model];
    }

    /**
     * 获取嵌入模型实例.
     */
    public function getEmbeddingModel(string $model): EmbeddingInterface
    {
        if ($model === '') {
            $model = $this->defaultEmbeddingModel;
        }

        // 检查缓存
        if (isset($this->models[ModelType::EMBEDDING][$model])) {
            return $this->models[ModelType::EMBEDDING][$model];
        }

        // 如果模型未缓存，创建模型
        $modelConfig = $this->config->get('odin.llm.models.' . $model);
        if (empty($modelConfig)) {
            throw new InvalidArgumentException(sprintf('Embedding Model %s is not defined.', $model));
        }

        $this->addModel($model, $modelConfig);

        if (! isset($this->models[ModelType::EMBEDDING][$model])) {
            throw new InvalidArgumentException(sprintf('Failed to create Embedding Model %s.', $model));
        }

        return $this->models[ModelType::EMBEDDING][$model];
    }

    /**
     * 获取所有模型实例.
     */
    public function getModels(string $type = ''): array
    {
        if ($type === ModelType::EMBEDDING) {
            return $this->models[ModelType::EMBEDDING] ?? [];
        }
        if ($type === ModelType::CHAT) {
            return $this->models[ModelType::CHAT] ?? [];
        }
        return $this->models;
    }

    /**
     * 添加模型实例.
     */
    public function addModel(string $model, array $item): void
    {
        $implementation = $item['implementation'] ?? '';
        if (! class_exists($implementation)) {
            throw new InvalidArgumentException(sprintf('Implementation %s is not defined.', $implementation));
        }

        // 获取全局模型配置和API配置
        $generalModelOptions = $this->config->get('odin.llm.general_model_options', []);
        $generalApiOptions = $this->config->get('odin.llm.general_api_options', []);

        // 全局配置可以被模型配置覆盖
        $modelOptionsArray = array_merge($generalModelOptions, $item['model_options'] ?? []);
        $apiOptionsArray = array_merge($generalApiOptions, $item['api_options'] ?? []);

        // 创建选项对象
        $modelOptions = new ModelOptions($modelOptionsArray);
        $apiOptions = new ApiOptions($apiOptionsArray);

        // 获取配置
        $config = $item['config'] ?? [];

        // 获取实际的端点名称，优先使用模型配置中的model字段
        $endpoint = empty($item['model']) ? $model : $item['model'];

        // 使用ModelFactory创建模型实例
        $modelObject = ModelFactory::create(
            $implementation,
            $endpoint,
            $config,
            $modelOptions,
            $apiOptions,
            $this->logger
        );

        // 根据模型类型缓存实例
        if ($modelOptions->isEmbedding() && $modelObject instanceof EmbeddingInterface) {
            $this->models[ModelType::EMBEDDING][$model] = $modelObject;
        }
        if ($modelOptions->isChat() && $modelObject instanceof ModelInterface) {
            $this->models[ModelType::CHAT][$model] = $modelObject;
        }
    }
}

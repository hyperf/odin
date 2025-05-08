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

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Request\CompletionRequest;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Api\Response\TextCompletionResponse;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Contract\Model\EmbeddingInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Exception\LLMException\Model\LLMEmbeddingNotSupportedException;
use Hyperf\Odin\Exception\LLMException\Model\LLMFunctionCallNotSupportedException;
use Hyperf\Odin\Exception\LLMException\Model\LLMModalityNotSupportedException;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Utils\MessageUtil;
use Hyperf\Odin\Utils\ToolUtil;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 模型抽象基类，实现模型的通用行为.
 */
abstract class AbstractModel implements ModelInterface, EmbeddingInterface
{
    /**
     * 错误处理器.
     */
    protected ?ErrorHandlerInterface $errorHandler = null;

    /**
     * API请求选项.
     */
    protected ApiOptions $apiRequestOptions;

    /**
     * 模型选项.
     */
    protected ModelOptions $modelOptions;

    /**
     * 构造函数.
     */
    public function __construct(
        protected string $model,
        protected array $config,
        protected ?LoggerInterface $logger = null
    ) {
        // 初始化，如果有需要修改的再修改
        $this->modelOptions = new ModelOptions();
        $this->apiRequestOptions = new ApiOptions();
    }

    /**
     * 聊天补全API.
     */
    public function chat(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionResponse {
        try {
            // 首先进行模型能力检测
            $this->checkFunctionCallSupport($tools);
            $this->checkMultiModalSupport($messages);

            $client = $this->getClient();
            $chatRequest = new ChatCompletionRequest($messages, $this->model, $temperature, $maxTokens, $stop, $tools, false);
            $chatRequest->setFrequencyPenalty($frequencyPenalty);
            $chatRequest->setPresencePenalty($presencePenalty);
            $chatRequest->setBusinessParams($businessParams);
            return $client->chatCompletions($chatRequest);
        } catch (Throwable $e) {
            $context = $this->createErrorContext([
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stop' => $stop,
                'tools' => $tools,
                'is_stream' => false,
                'frequency_penalty' => $frequencyPenalty,
                'presence_penalty' => $presencePenalty,
                'business_params' => $businessParams,
            ]);
            throw $this->handleException($e, $context);
        }
    }

    /**
     * 流式聊天补全API.
     */
    public function chatStream(
        array $messages,
        float $temperature = 0.9,
        int $maxTokens = 0,
        array $stop = [],
        array $tools = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): ChatCompletionStreamResponse {
        try {
            // 首先进行模型能力检测
            $this->checkFunctionCallSupport($tools);
            $this->checkMultiModalSupport($messages);

            $client = $this->getClient();
            $chatRequest = new ChatCompletionRequest($messages, $this->model, $temperature, $maxTokens, $stop, $tools, true);
            $chatRequest->setFrequencyPenalty($frequencyPenalty);
            $chatRequest->setPresencePenalty($presencePenalty);
            $chatRequest->setBusinessParams($businessParams);
            return $client->chatCompletionsStream($chatRequest);
        } catch (Throwable $e) {
            $context = $this->createErrorContext([
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stop' => $stop,
                'tools' => $tools,
                'is_stream' => true,
                'frequency_penalty' => $frequencyPenalty,
                'presence_penalty' => $presencePenalty,
                'business_params' => $businessParams,
            ]);
            throw $this->handleException($e, $context);
        }
    }

    /**
     * 文本补全API.
     */
    public function completions(
        string $prompt,
        float $temperature = 0.9,
        int $maxTokens = 16,
        array $stop = [],
        float $frequencyPenalty = 0.0,
        float $presencePenalty = 0.0,
        array $businessParams = [],
    ): TextCompletionResponse {
        $client = $this->getClient();
        $chatRequest = new CompletionRequest($this->model, $prompt, $temperature, $maxTokens, $stop);
        $chatRequest->setFrequencyPenalty($frequencyPenalty);
        $chatRequest->setPresencePenalty($presencePenalty);
        $chatRequest->setBusinessParams($businessParams);
        return $client->completions($chatRequest);
    }

    /**
     * 生成文本的嵌入向量.
     */
    public function embedding(array|string $input, ?string $encoding_format = 'float', ?string $user = null): Embedding
    {
        $response = $this->embeddings($input, $encoding_format, $user);

        // 从响应中提取嵌入向量
        $embeddings = [];
        foreach ($response->getData() as $embedding) {
            $embeddings[] = $embedding->getEmbedding();
        }

        // 通常只有一个嵌入向量，但如果有多个，我们只使用第一个
        if (empty($embeddings)) {
            return new Embedding([]);
        }

        return new Embedding($embeddings[0]);
    }

    public function embeddings(array|string $input, ?string $encoding_format = 'float', ?string $user = null): EmbeddingResponse
    {
        try {
            // 检查模型是否支持嵌入功能
            $this->checkEmbeddingSupport();

            $client = $this->getClient();
            $embeddingRequest = new EmbeddingRequest(
                input: $input,
                model: $this->model
            );

            return $client->embeddings($embeddingRequest);
        } catch (Throwable $e) {
            $context = [
                'model' => $this->model,
                'input' => $input,
            ];
            throw $this->handleException($e, $context);
        }
    }

    /**
     * 获取模型名称.
     *
     * @return string 模型名称
     */
    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * 获取向量大小.
     *
     * @return int 向量大小
     */
    public function getVectorSize(): int
    {
        return $this->modelOptions->getVectorSize();
    }

    /**
     * 设置模型选项.
     */
    public function setModelOptions(ModelOptions $modelOptions): self
    {
        $this->modelOptions = $modelOptions;
        return $this;
    }

    /**
     * 设置API请求选项.
     */
    public function setApiRequestOptions(ApiOptions $apiRequestOptions): self
    {
        $this->apiRequestOptions = $apiRequestOptions;
        return $this;
    }

    /**
     * 获取API请求选项.
     */
    public function getApiRequestOptions(): ApiOptions
    {
        return $this->apiRequestOptions;
    }

    public function getModelOptions(): ModelOptions
    {
        return $this->modelOptions;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 创建错误上下文.
     *
     * @param array $params 请求参数
     * @return array 错误上下文
     */
    protected function createErrorContext(array $params): array
    {
        // 处理消息过滤
        if (isset($params['messages'])) {
            $params['messages'] = MessageUtil::filter($params['messages']);
        }

        // 处理工具过滤
        if (isset($params['tools'])) {
            $params['tools'] = ToolUtil::filter($params['tools']);
        }

        // 添加模型和配置信息
        $params['model'] = $this->model;
        $params['config'] = $this->config;

        return $params;
    }

    /**
     * 获取错误处理器.
     */
    protected function getErrorHandler(): ErrorHandlerInterface
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = new LLMErrorHandler($this->logger);
        }
        return $this->errorHandler;
    }

    /**
     * 检查模型是否支持函数调用.
     */
    protected function checkFunctionCallSupport(array $tools): void
    {
        if (! empty($tools) && ! $this->modelOptions->supportsFunctionCall()) {
            throw new LLMFunctionCallNotSupportedException(
                sprintf('模型 %s 不支持函数调用功能', $this->model),
                null,
                $this->model
            );
        }
    }

    /**
     * 检查模型是否支持多模态输入.
     * @param array<MessageInterface> $messages
     */
    protected function checkMultiModalSupport(array $messages): void
    {
        if ($this->containsMultiModalContent($messages) && ! $this->modelOptions->isMultiModal()) {
            throw new LLMModalityNotSupportedException(
                sprintf('模型 %s 不支持多模态输入', $this->model),
                null,
                $this->model
            );
        }
    }

    /**
     * 检查消息中是否包含多模态内容.
     *
     * @param array<MessageInterface> $messages 消息数组
     * @return bool 是否包含多模态内容
     */
    protected function containsMultiModalContent(array $messages): bool
    {
        foreach ($messages as $message) {
            // 检查消息的content字段是否为数组（多模态内容通常以数组形式提供）
            if ($message instanceof UserMessage && $message->hasImageMultiModal()) {
                return true;
            }
        }
        return false;
    }

    /**
     * 处理异常，转换为标准的LLM异常.
     */
    protected function handleException(Throwable $exception, array $context = []): LLMException
    {
        return $this->getErrorHandler()->handle($exception, $context);
    }

    /**
     * 获取客户端实例，由子类实现.
     */
    abstract protected function getClient(): ClientInterface;

    /**
     * 处理API基础URL，确保包含正确的版本路径.
     * 如果base_url已经包含版本路径，则不做任何处理
     * 如果没有包含，则根据模型类型添加相应的版本路径.
     */
    protected function processApiBaseUrl(array &$config): void
    {
        // 如果没有base_url，不做处理
        if (empty($config['base_url'])) {
            return;
        }

        // 检查base_url是否已包含API路径
        $baseUrl = $config['base_url'];
        if ($this->hasApiPathInBaseUrl($baseUrl)) {
            return;
        }

        // 获取当前模型的API版本路径
        $versionPath = $this->getApiVersionPath();
        if (! empty($versionPath)) {
            // 确保base_url和versionPath之间只有一个斜杠
            $baseUrl = rtrim($baseUrl, '/');
            $versionPath = ltrim($versionPath, '/');
            $config['base_url'] = $baseUrl . '/' . $versionPath;
        }
    }

    /**
     * 获取API版本路径.
     * 默认返回空，由子类覆盖实现.
     */
    protected function getApiVersionPath(): string
    {
        return '';
    }

    /**
     * 检查基础URL是否已包含API路径.
     */
    protected function hasApiPathInBaseUrl(string $baseUrl): bool
    {
        $urlParts = parse_url($baseUrl);
        return ! empty($urlParts['path']) && $urlParts['path'] !== '/';
    }

    /**
     * 检查模型是否支持嵌入功能.
     */
    protected function checkEmbeddingSupport(): void
    {
        if (! $this->modelOptions->isEmbedding()) {
            throw new LLMEmbeddingNotSupportedException(
                sprintf('模型 %s 不支持嵌入功能', $this->model),
                null,
                $this->model
            );
        }
    }
}

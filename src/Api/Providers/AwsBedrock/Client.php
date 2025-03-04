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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;
use Hyperf\Odin\Api\Providers\AbstractClient;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Exception\LLMException\Api\LLMRateLimitException;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class Client extends AbstractClient
{
    /**
     * AWS Bedrock 运行时客户端.
     */
    private BedrockRuntimeClient $bedrockClient;

    /**
     * 构造函数.
     */
    public function __construct(AwsBedrockConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null)
    {
        if (! $requestOptions) {
            $requestOptions = new ApiOptions();
        }
        parent::__construct($config, $requestOptions, $logger);
    }

    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareRequestBody($chatRequest);

            $args = [
                'body' => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
                'modelId' => $modelId,
                'contentType' => 'application/json',
                'accept' => 'application/json',
                '@http' => $this->getHttpArgs(
                    false,
                    $this->requestOptions->getProxy()
                ),
            ];

            // 记录请求前日志
            $this->logger?->debug('AwsBedrockChatRequest', [
                'model_id' => $modelId,
                'args' => $args,
            ]);

            // 调用模型
            $result = $this->bedrockClient->invokeModel($args);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            $responseBody = json_decode($result['body']->getContents(), true);

            // 转换为符合PSR-7标准的Response对象
            $psrResponse = ResponseHandler::convertToPsrResponse($responseBody, $chatRequest->getModel());
            $chatCompletionResponse = new ChatCompletionResponse($psrResponse, $this->logger);

            $this->logger?->debug('AwsBedrockChatResponse', [
                'model_id' => $modelId,
                'duration_ms' => $duration,
                'content' => $chatCompletionResponse->getContent(),
            ]);

            return $chatCompletionResponse;
        } catch (AwsException $e) {
            throw $this->convertAwsException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $startTime = microtime(true);

        try {
            // 验证请求参数
            $chatRequest->validate();

            // 获取模型ID和转换请求参数
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareRequestBody($chatRequest);

            $args = [
                'body' => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
                'modelId' => $modelId,
                'contentType' => 'application/json',
                'accept' => 'application/json',
                '@http' => $this->getHttpArgs(true, $this->requestOptions->getProxy()),
            ];

            // 记录请求前日志
            $this->logger?->debug('AwsBedrockStreamRequest', [
                'model_id' => $modelId,
                'args' => $args,
            ]);

            // 使用流式响应调用模型
            $result = $this->bedrockClient->invokeModelWithResponseStream($args);

            $firstResponseTime = microtime(true);
            $firstResponseDuration = round(($firstResponseTime - $startTime) * 1000); // 毫秒

            // 记录首次响应日志
            $this->logger?->debug('AwsBedrockStreamFirstResponse', [
                'model_id' => $modelId,
                'first_response_ms' => $firstResponseDuration,
            ]);

            // 创建 AWS Bedrock 格式转换器，负责将 AWS Bedrock 格式转换为 OpenAI 格式
            $bedrockConverter = new AwsBedrockFormatConverter($result, $this->logger);

            // 创建流式响应对象并返回
            return new ChatCompletionStreamResponse(logger: $this->logger, streamIterator: $bedrockConverter);
        } catch (AwsException $e) {
            throw $this->convertAwsException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    public function embeddings(EmbeddingRequest $embeddingRequest): EmbeddingResponse
    {
        // Embedding实现将在后续添加
        throw new RuntimeException('Embeddings are not implemented yet');
    }

    /**
     * 初始化客户端.
     *
     * 重写父类的方法，因为 AWS Bedrock 使用 SDK 而不是 HTTP 客户端
     */
    protected function initClient(): void
    {
        // AWS Bedrock 不需要调用父类的 initClient 方法，因为它不使用 HTTP 客户端

        /** @var AwsBedrockConfig $config */
        $config = $this->config;

        // 初始化 AWS Bedrock 客户端
        $this->bedrockClient = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $config->region,
            'credentials' => [
                'key' => $config->accessKey,
                'secret' => $config->secretKey,
            ],
        ]);
    }

    protected function buildChatCompletionsUrl(): string
    {
        // AWS Bedrock不使用HTTP URL，它使用AWS SDK
        return '';
    }

    protected function buildEmbeddingsUrl(): string
    {
        // AWS Bedrock不使用HTTP URL，它使用AWS SDK
        return '';
    }

    /**
     * 构建文本补全API的URL.
     */
    protected function buildCompletionsUrl(): string
    {
        // AWS Bedrock不使用HTTP URL，它使用AWS SDK
        return '';
    }

    /**
     * 获取认证头信息.
     */
    protected function getAuthHeaders(): array
    {
        // AWS Bedrock不使用标准的认证头，而是通过AWS SDK认证
        return [];
    }

    /**
     * 转换通用异常为LLM异常.
     */
    protected function convertException(Throwable $exception, array $context = []): LLMException
    {
        $message = $exception->getMessage();
        $code = (int) $exception->getCode();

        // 判断异常类型并返回对应的LLM异常
        if (str_contains($message, 'timed out')) {
            return new LLMReadTimeoutException($message, $exception);
        }

        if (str_contains($message, 'rate limit') || str_contains($message, 'throttled')) {
            return new LLMRateLimitException($message, $exception, $code);
        }

        if ($code >= 400 && $code < 500) {
            return new LLMInvalidRequestException($message, $exception, $code);
        }

        if ($code >= 500) {
            // 对于服务器错误，使用通用API异常
            return new LLMApiException($message, $code, $exception, 0, $code);
        }

        // 默认返回通用异常
        return new LLMApiException($message, $code, $exception);
    }

    /**
     * 准备HTTP配置参数.
     */
    private function getHttpArgs(bool $stream = false, ?string $proxy = null): array
    {
        $http = [];
        if ($stream) {
            $http['stream'] = true;
        }
        if ($proxy) {
            $http['proxy'] = $proxy;
        }
        return $http;
    }

    /**
     * 转换AWS异常为LLM异常.
     */
    private function convertAwsException(AwsException $e): LLMException
    {
        return $this->convertException($e, [
            'aws_error_type' => $e->getAwsErrorType(),
            'aws_error_code' => $e->getAwsErrorCode(),
        ]);
    }

    /**
     * 准备请求体数据.
     */
    private function prepareRequestBody(ChatCompletionRequest $chatRequest): array
    {
        // 转换消息并获取系统提示
        $convertedData = MessageConverter::convertMessages($chatRequest->getMessages());
        $messages = $convertedData['messages'];
        $systemMessage = $convertedData['system'];

        // 获取请求参数
        $maxTokens = $chatRequest->getMaxTokens();
        $temperature = $chatRequest->getTemperature();
        $stop = $chatRequest->getStop();

        // 准备请求体 - 符合Claude API格式
        $requestBody = [
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => $maxTokens > 0 ? $maxTokens : 8192,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        // 添加停止词
        if (! empty($stop)) {
            $requestBody['stop_sequences'] = $stop;
        }

        // 添加系统提示
        if (! empty($systemMessage)) {
            $requestBody['system'] = $systemMessage;
        }

        // 添加工具调用支持
        if (! empty($chatRequest->getTools())) {
            $requestBody['tools'] = MessageConverter::convertTools($chatRequest->getTools());
            // 添加工具选择策略
            if (! empty($requestBody['tools'])) {
                $requestBody['tool_choice']['type'] = 'auto';
            }
        }

        return $requestBody;
    }
}

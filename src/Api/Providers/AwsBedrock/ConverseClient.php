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

use Aws\Exception\AwsException;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Utils\LogUtil;
use Throwable;

class ConverseClient extends Client
{
    public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            // 获取模型ID和转换请求参数
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareConverseRequestBody($chatRequest);

            $args = [
                'modelId' => $modelId,
                '@http' => $this->getHttpArgs(
                    false,
                    $this->requestOptions->getProxy()
                ),
            ];
            $args = array_merge($requestBody, $args);

            // 记录请求前日志
            $this->logger?->debug('AwsBedrockConverseRequest', [
                'model_id' => $modelId,
                'args' => LogUtil::formatLongText($args),
            ]);

            // 调用模型
            $result = $this->bedrockClient->converse($args);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            // 转换为符合PSR-7标准的Response对象
            $psrResponse = ResponseHandler::convertConverseToPsrResponse($result['output'] ?? [], $result['usage'] ?? [], $chatRequest->getModel());
            $chatCompletionResponse = new ChatCompletionResponse($psrResponse, $this->logger);

            $this->logger?->debug('AwsBedrockConverseResponse', [
                'model_id' => $modelId,
                'duration_ms' => $duration,
                'usage' => $result['usage'] ?? [],
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
            $requestBody = $this->prepareConverseRequestBody($chatRequest);

            $args = [
                'modelId' => $modelId,
                '@http' => $this->getHttpArgs(
                    true,
                    $this->requestOptions->getProxy()
                ),
            ];
            $args = array_merge($requestBody, $args);

            // 记录请求前日志
            $this->logger?->debug('AwsBedrockConverseStreamRequest', [
                'model_id' => $modelId,
                'args' => LogUtil::formatLongText($args),
            ]);

            // 使用流式响应调用模型
            $result = $this->bedrockClient->converseStream($args);

            $firstResponseTime = microtime(true);
            $firstResponseDuration = round(($firstResponseTime - $startTime) * 1000); // 毫秒

            // 记录首次响应日志
            $this->logger?->debug('AwsBedrockConverseStreamFirstResponse', [
                'model_id' => $modelId,
                'first_response_ms' => $firstResponseDuration,
            ]);

            // 创建 AWS Bedrock 格式转换器，负责将 AWS Bedrock 格式转换为 OpenAI 格式
            $bedrockConverter = new AwsBedrockConverseFormatConverter($result, $this->logger, $modelId);

            // 创建流式响应对象并返回
            return new ChatCompletionStreamResponse(logger: $this->logger, streamIterator: $bedrockConverter);
        } catch (AwsException $e) {
            throw $this->convertAwsException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    protected function createConverter(): ConverterInterface
    {
        return new ConverseConverter();
    }

    /**
     * 准备 Converse API 请求体.
     */
    private function prepareConverseRequestBody(ChatCompletionRequest $chatRequest): array
    {
        $messages = [];
        $systemMessage = '';
        foreach ($chatRequest->getMessages() as $message) {
            if (! $message instanceof MessageInterface) {
                continue;
            }
            match (true) {
                $message instanceof SystemMessage => $systemMessage = $this->converter->convertSystemMessage($message),
                $message instanceof ToolMessage => $messages[] = $this->converter->convertToolMessage($message),
                $message instanceof AssistantMessage => $messages[] = $this->converter->convertAssistantMessage($message),
                $message instanceof UserMessage => $messages[] = $this->converter->convertUserMessage($message),
            };
        }

        // 获取请求参数
        $maxTokens = $chatRequest->getMaxTokens();
        $temperature = $chatRequest->getTemperature();
        $stop = $chatRequest->getStop();

        // 准备请求体 - 符合 Converse API 格式
        $requestBody = [
            'messages' => $messages,
        ];

        // 添加系统提示
        if (! empty($systemMessage)) {
            $requestBody['system'] = $systemMessage;
        }

        // 添加推理配置
        $inferenceConfig = [
            'temperature' => $temperature,
        ];

        // 添加最大令牌数
        if ($maxTokens > 0) {
            $inferenceConfig['maxTokens'] = $maxTokens;
        }

        // 如果有推理配置参数，添加到请求体
        if (! empty($inferenceConfig)) {
            $requestBody['inferenceConfig'] = $inferenceConfig;
        }

        // 添加停止序列
        if (! empty($stop)) {
            $requestBody['additionalModelRequestFields'] = [
                'stop_sequences' => $stop,
            ];
        }

        // 添加工具调用支持
        if (! empty($chatRequest->getTools())) {
            $tools = $this->converter->convertTools($chatRequest->getTools(), $chatRequest->isToolsCache());
            if (! empty($tools)) {
                $requestBody['toolConfig'] = [
                    'tools' => $tools,
                ];
            }
        }

        return $requestBody;
    }
}

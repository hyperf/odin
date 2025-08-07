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
use Hyperf\Odin\Api\Providers\AwsBedrock\Cache\AwsBedrockCachePointManager;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Event\AfterChatCompletionsEvent;
use Hyperf\Odin\Event\AfterChatCompletionsStreamEvent;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Utils\EventUtil;
use Hyperf\Odin\Utils\LoggingConfigHelper;
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

            // 生成请求ID
            $requestId = $this->generateRequestId();

            $args = [
                'modelId' => $modelId,
                '@http' => $this->getHttpArgs(
                    false,
                    $this->requestOptions->getProxy()
                ),
            ];
            $args = array_merge($requestBody, $args);

            // 记录请求前日志
            $this->logger?->info('AwsBedrockConverseRequest', LoggingConfigHelper::filterAndFormatLogData([
                'request_id' => $requestId,
                'model_id' => $modelId,
                'args' => $args,
                'token_estimate' => $chatRequest->getTokenEstimateDetail(),
            ], $this->requestOptions));

            // 调用模型
            $result = $this->bedrockClient->converse($args);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 毫秒

            // 转换为符合PSR-7标准的Response对象
            $psrResponse = ResponseHandler::convertConverseToPsrResponse($result['output'] ?? [], $result['usage'] ?? [], $chatRequest->getModel());
            $chatCompletionResponse = new ChatCompletionResponse($psrResponse, $this->logger);

            $performanceFlag = LogUtil::getPerformanceFlag($duration);
            $logData = [
                'request_id' => $requestId,
                'model_id' => $modelId,
                'duration_ms' => $duration,
                'usage' => $result['usage'] ?? [],
                'content' => $chatCompletionResponse->getContent(),
                'response_headers' => $result['@metadata']['headers'] ?? [],
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('AwsBedrockConverseResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            EventUtil::dispatch(new AfterChatCompletionsEvent($chatRequest, $chatCompletionResponse, $duration));

            return $chatCompletionResponse;
        } catch (AwsException $e) {
            throw $this->convertAwsException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
    {
        $chatRequest->validate();
        $startTime = microtime(true);

        try {
            // 获取模型ID和转换请求参数
            $modelId = $chatRequest->getModel();
            $requestBody = $this->prepareConverseRequestBody($chatRequest);

            // 生成请求ID
            $requestId = $this->generateRequestId();

            $args = [
                'modelId' => $modelId,
                '@http' => $this->getHttpArgs(
                    true,
                    $this->requestOptions->getProxy()
                ),
            ];
            $args = array_merge($requestBody, $args);

            // 记录请求前日志
            $this->logger?->info('AwsBedrockConverseStreamRequest', LoggingConfigHelper::filterAndFormatLogData([
                'request_id' => $requestId,
                'model_id' => $modelId,
                'args' => $args,
                'token_estimate' => $chatRequest->getTokenEstimateDetail(),
            ], $this->requestOptions));

            // 使用流式响应调用模型
            $result = $this->bedrockClient->converseStream($args);

            $firstResponseTime = microtime(true);
            $firstResponseDuration = round(($firstResponseTime - $startTime) * 1000); // 毫秒

            // 记录首次响应日志
            $performanceFlag = LogUtil::getPerformanceFlag($firstResponseDuration);
            $logData = [
                'request_id' => $requestId,
                'model_id' => $modelId,
                'first_response_ms' => $firstResponseDuration,
                'response_headers' => $result['@metadata']['headers'] ?? [],
                'performance_flag' => $performanceFlag,
            ];

            $this->logger?->info('AwsBedrockConverseStreamFirstResponse', LoggingConfigHelper::filterAndFormatLogData($logData, $this->requestOptions));

            // 创建 AWS Bedrock 格式转换器，负责将 AWS Bedrock 格式转换为 OpenAI 格式
            $bedrockConverter = new AwsBedrockConverseFormatConverter($result, $this->logger, $modelId);

            $chatCompletionStreamResponse = new ChatCompletionStreamResponse(logger: $this->logger, streamIterator: $bedrockConverter);
            $chatCompletionStreamResponse->setAfterChatCompletionsStreamEvent(new AfterChatCompletionsStreamEvent($chatRequest, $firstResponseDuration));

            return $chatCompletionStreamResponse;
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
        if ($this->isAutoCache()) {
            $cachePointManager = new AwsBedrockCachePointManager($this->getAutoCacheConfig());
            $cachePointManager->configureCachePoints($chatRequest);
        }

        $messages = [];
        $systemMessage = '';
        $originalMessages = $chatRequest->getMessages();

        // Process messages with tool call grouping logic
        $processedMessages = $this->processMessagesWithToolGrouping($originalMessages);

        foreach ($processedMessages as $message) {
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

        if (! empty($chatRequest->getThinking())) {
            $requestBody['thinking'] = $chatRequest->getThinking();
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

    /**
     * Process messages and group tool results for multi-tool calls.
     *
     * When an AssistantMessage contains multiple tool calls, Claude's Converse API
     * requires all corresponding tool results to be in the same user message.
     *
     * @param array $messages Original messages array
     * @return array Processed messages with grouped tool results
     */
    private function processMessagesWithToolGrouping(array $messages): array
    {
        $processedMessages = [];
        $messageCount = count($messages);

        for ($i = 0; $i < $messageCount; ++$i) {
            $message = $messages[$i];

            // Add non-assistant messages as-is
            if (! $message instanceof AssistantMessage) {
                $processedMessages[] = $message;
                continue;
            }

            // Add the assistant message
            $processedMessages[] = $message;

            // Check if this assistant message has multiple tool calls
            if (! $message->hasToolCalls() || count($message->getToolCalls()) <= 1) {
                continue;
            }

            // Collect the expected tool call IDs
            $expectedToolIds = [];
            foreach ($message->getToolCalls() as $toolCall) {
                $expectedToolIds[] = $toolCall->getId();
            }

            // Look for consecutive tool messages that match the expected tool IDs
            $collectedToolMessages = [];
            $j = $i + 1;

            while ($j < $messageCount && $messages[$j] instanceof ToolMessage) {
                $toolMessage = $messages[$j];
                $toolCallId = $toolMessage->getToolCallId();

                // Check if this tool message belongs to the current assistant message
                if (in_array($toolCallId, $expectedToolIds)) {
                    $collectedToolMessages[] = $toolMessage;
                    ++$j;
                } else {
                    // This tool message doesn't belong to current assistant message
                    break;
                }
            }

            // If we found multiple tool messages, merge them
            if (count($collectedToolMessages) > 1) {
                $mergedToolMessage = $this->createMergedToolMessage($collectedToolMessages);
                $processedMessages[] = $mergedToolMessage;
                // Skip the original tool messages since we've merged them
                $i = $j - 1;
            }
        }

        return $processedMessages;
    }

    /**
     * Create a merged tool message from multiple tool messages.
     *
     * @param array $toolMessages Array of ToolMessage instances
     * @return ToolMessage Merged tool message
     */
    private function createMergedToolMessage(array $toolMessages): ToolMessage
    {
        return new MergedToolMessage($toolMessages);
    }
}

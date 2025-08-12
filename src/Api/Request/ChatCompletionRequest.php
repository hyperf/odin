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

namespace Hyperf\Odin\Api\Request;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Contract\Api\Request\RequestInterface;
use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Exception\InvalidArgumentException;
use Hyperf\Odin\Exception\LLMException\LLMModelException;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Utils\MessageUtil;
use Hyperf\Odin\Utils\TokenEstimator;
use Hyperf\Odin\Utils\ToolUtil;

class ChatCompletionRequest implements RequestInterface
{
    private ?array $filterMessages = null;

    private bool $streamContentEnabled = false;

    private float $frequencyPenalty = 0.0;

    private float $presencePenalty = 0.0;

    private bool $includeBusinessParams = false;

    private array $businessParams = [];

    private bool $toolsCache = false;

    private ?int $systemTokenEstimate = null;

    /**
     * 工具的token估算数量.
     */
    private ?int $toolsTokenEstimate = null;

    /**
     * 所有消息和工具的总token估算数量.
     */
    private ?int $totalTokenEstimate = null;

    private bool $streamIncludeUsage = false;

    private ?array $thinking = null;

    private array $optionKeyMaps = [];

    public function __construct(
        /** @var MessageInterface[] $messages */
        protected array $messages,
        protected string $model = '',
        protected float $temperature = 0.5,
        protected int $maxTokens = 0,
        protected array $stop = [],
        protected array $tools = [],
        protected bool $stream = false,
    ) {}

    public function addTool(ToolDefinition $toolDefinition): void
    {
        $this->tools[$toolDefinition->getName()] = $toolDefinition;
    }

    public function setOptionKeyMaps(array $optionKeyMaps): void
    {
        $this->optionKeyMaps = $optionKeyMaps;
    }

    public function validate(): void
    {
        if (empty($this->model)) {
            throw new InvalidArgumentException('Model is required.');
        }
        // 温度只能在 [0,1]
        if ($this->temperature < 0 || $this->temperature > 1) {
            throw new InvalidArgumentException('Temperature must be between 0 and 1.');
        }
        $this->filterMessages = MessageUtil::filter($this->messages);
        if (empty($this->filterMessages)) {
            throw new InvalidArgumentException('Messages is required.');
        }

        // 验证消息序列是否符合API规范
        $this->validateMessageSequence();
    }

    public function createOptions(): array
    {
        $json = [
            'messages' => $this->filterMessages ?? MessageUtil::filter($this->messages),
            'model' => $this->model,
            'temperature' => $this->temperature,
            'stream' => $this->stream,
        ];
        if ($this->maxTokens > 0) {
            if (isset($this->optionKeyMaps['max_tokens'])) {
                $json[$this->optionKeyMaps['max_tokens']] = $this->maxTokens;
            } else {
                $json['max_tokens'] = $this->maxTokens;
            }
        }
        if (! empty($this->stop)) {
            $json['stop'] = $this->stop;
        }
        $tools = ToolUtil::filter($this->tools);
        if (! empty($tools)) {
            $json['tools'] = $tools;
            $json['tool_choice'] = 'auto';
        }
        if ($this->frequencyPenalty > 0) {
            $json['frequency_penalty'] = $this->frequencyPenalty;
        }
        if ($this->presencePenalty > 0) {
            $json['presence_penalty'] = $this->presencePenalty;
        }
        if ($this->includeBusinessParams && ! empty($this->businessParams)) {
            $json['business_params'] = $this->businessParams;
        }
        if ($this->stream && $this->streamIncludeUsage) {
            $json['stream_options'] = [
                'include_usage' => true,
            ];
        }
        if (! empty($this->thinking)) {
            $json['thinking'] = $this->thinking;
        }

        return [
            RequestOptions::JSON => $json,
            RequestOptions::STREAM => $this->stream,
        ];
    }

    /**
     * 为所有消息和工具计算token估算
     * 对于已经有估算的消息不会重新计算.
     *
     * @return int 所有消息和工具的总token数量
     */
    public function calculateTokenEstimates(): int
    {
        if ($this->totalTokenEstimate) {
            return $this->totalTokenEstimate;
        }
        $estimator = new TokenEstimator($this->model);
        $totalTokens = 0;

        // 为每个消息计算token
        foreach ($this->messages as $message) {
            if ($message->getTokenEstimate() === null) {
                $tokenCount = $estimator->estimateMessageTokens($message);
                $message->setTokenEstimate($tokenCount);
                if ($message instanceof SystemMessage) {
                    $this->systemTokenEstimate = $tokenCount;
                }
            }
            $totalTokens += $message->getTokenEstimate();
        }

        // 为工具计算token
        if ($this->toolsTokenEstimate === null && ! empty($this->tools)) {
            $this->toolsTokenEstimate = $estimator->estimateToolsTokens($this->tools);
        }

        if ($this->toolsTokenEstimate !== null) {
            $totalTokens += $this->toolsTokenEstimate;
        }

        // 保存总token估算结果
        $this->totalTokenEstimate = $totalTokens;

        return $totalTokens;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function setThinking(?array $thinking): void
    {
        $this->thinking = $thinking;
    }

    public function setFrequencyPenalty(float $frequencyPenalty): void
    {
        $this->frequencyPenalty = $frequencyPenalty;
    }

    public function setPresencePenalty(float $presencePenalty): void
    {
        $this->presencePenalty = $presencePenalty;
    }

    public function setBusinessParams(array $businessParams): void
    {
        $this->businessParams = $businessParams;
    }

    public function getBusinessParams(): array
    {
        return $this->businessParams;
    }

    public function setIncludeBusinessParams(bool $includeBusinessParams): void
    {
        $this->includeBusinessParams = $includeBusinessParams;
    }

    public function setStream(bool $stream): void
    {
        $this->stream = $stream;
    }

    public function isStream(): bool
    {
        return $this->stream;
    }

    public function isStreamContentEnabled(): bool
    {
        return $this->streamContentEnabled;
    }

    public function setStreamContentEnabled(bool $streamContentEnabled): void
    {
        $this->streamContentEnabled = $streamContentEnabled;
    }

    public function setStreamIncludeUsage(bool $streamIncludeUsage): void
    {
        $this->streamIncludeUsage = $streamIncludeUsage;
    }

    /**
     * 获取消息列表.
     *
     * @return array<MessageInterface> 消息列表
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * 获取工具列表.
     *
     * @return array 工具列表
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * 获取模型名称.
     *
     * @return string 模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function getStop(): array
    {
        return $this->stop;
    }

    public function getThinking(): ?array
    {
        return $this->thinking;
    }

    public function isToolsCache(): bool
    {
        return $this->toolsCache;
    }

    public function setToolsCache(bool $toolsCache): void
    {
        $this->toolsCache = $toolsCache;
    }

    public function getSystemTokenEstimate(): ?int
    {
        return $this->systemTokenEstimate;
    }

    public function setTemperature(float $temperature): void
    {
        $this->temperature = $temperature;
    }

    /**
     * 获取工具的token估算数量.
     *
     * @return null|int 工具的token估算数量
     */
    public function getToolsTokenEstimate(): ?int
    {
        return $this->toolsTokenEstimate;
    }

    /**
     * 获取所有消息和工具的总token估算数量.
     *
     * @return null|int 总token估算数量
     */
    public function getTotalTokenEstimate(): ?int
    {
        return $this->totalTokenEstimate;
    }

    public function getTokenEstimateDetail(): array
    {
        return [
            'total' => $this->totalTokenEstimate,
            'messages' => array_map(function (MessageInterface $message) {
                return $message->getTokenEstimate();
            }, $this->messages),
            'tools' => $this->toolsTokenEstimate,
        ];
    }

    public function toArray(): array
    {
        return [
            'messages' => $this->messages,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'stop' => $this->stop,
            'tools' => ToolUtil::filter($this->tools),
            'stream' => $this->stream,
            'stream_content_enabled' => $this->streamContentEnabled,
            'frequency_penalty' => $this->frequencyPenalty,
            'presence_penalty' => $this->presencePenalty,
            'include_business_params' => $this->includeBusinessParams,
            'business_params' => $this->businessParams,
            'tools_cache' => $this->toolsCache,
            'system_token_estimate' => $this->systemTokenEstimate,
            'tools_token_estimate' => $this->toolsTokenEstimate,
            'total_token_estimate' => $this->totalTokenEstimate,
            'stream_include_usage' => $this->streamIncludeUsage,
        ];
    }

    public function removeBigObject(): void
    {
        $this->tools = ToolUtil::filter($this->tools);
    }

    /**
     * 验证消息序列是否符合API规范.
     *
     * @throws LLMModelException 当消息序列不符合规范时抛出异常
     */
    private function validateMessageSequence(): void
    {
        $messages = $this->messages;
        if (empty($messages)) {
            return;
        }

        $previousMessage = null;
        $expectingToolResult = false;
        $pendingToolCallIds = [];

        foreach ($messages as $index => $message) {
            $role = $message->getRole();

            // 检查带有工具调用的assistant消息后是否跟随了tool消息
            if ($previousMessage && $previousMessage->getRole() === Role::Assistant && $role === Role::Assistant) {
                // 检查前一个assistant消息是否包含tool calls
                $hasToolCalls = false;
                $toolCalls = [];
                if (method_exists($previousMessage, 'getToolCalls')) {
                    $toolCalls = $previousMessage->getToolCalls();
                    $hasToolCalls = ! empty($toolCalls);
                }

                // 只有当前一个assistant消息包含tool calls时才报错
                if ($hasToolCalls) {
                    $previousContent = $this->truncateContent($previousMessage->getContent());
                    $currentContent = $this->truncateContent($message->getContent());

                    $errorMsg = 'Invalid message sequence: Assistant message with tool calls at position '
                        . ($index - 1) . " must be followed by tool result messages, not another assistant message.\n\n";

                    // 显示前一个assistant消息的详情
                    $errorMsg .= 'Message at position ' . ($index - 1) . " (assistant with tool calls):\n";
                    $errorMsg .= "Content: \"{$previousContent}\"\n";
                    $errorMsg .= 'Tool calls: ';
                    $toolInfo = array_map(function ($toolCall) {
                        $name = method_exists($toolCall, 'getName') ? $toolCall->getName() : 'unknown';
                        $id = method_exists($toolCall, 'getId') ? $toolCall->getId() : '';
                        return "{$name}(id:{$id})";
                    }, $toolCalls);
                    $errorMsg .= implode(', ', $toolInfo) . "\n";

                    // 显示当前assistant消息的详情
                    $errorMsg .= "\nMessage at position {$index} (assistant):\n";
                    $errorMsg .= "Content: \"{$currentContent}\"\n\n";

                    $errorMsg .= 'Solution: After an assistant message with tool_calls, you must provide tool result messages before the next assistant message.';

                    throw new LLMModelException($errorMsg);
                }
            }

            // 检查工具调用序列
            if ($role === Role::Assistant) {
                // 如果前一个assistant消息有tool_calls，现在应该处理工具结果
                if ($expectingToolResult && ! empty($pendingToolCallIds)) {
                    $currentContent = $this->truncateContent($message->getContent());

                    $errorMsg = 'Invalid message sequence: Expected tool result messages for pending tool_calls, '
                        . "but found another assistant message at position {$index}.\n\n";

                    $errorMsg .= 'Pending tool_call IDs: ' . implode(', ', $pendingToolCallIds) . "\n\n";

                    $errorMsg .= "Current assistant message at position {$index}:\n";
                    $errorMsg .= "Content: \"{$currentContent}\"\n\n";

                    $errorMsg .= 'Solution: You must provide tool result messages for each pending tool_call before adding another assistant message.';

                    throw new LLMModelException($errorMsg);
                }

                // 检查当前assistant消息是否有工具调用
                $toolCalls = method_exists($message, 'getToolCalls') ? $message->getToolCalls() : [];
                if (! empty($toolCalls)) {
                    $expectingToolResult = true;
                    $pendingToolCallIds = array_map(function ($toolCall) {
                        return $toolCall->getId();
                    }, $toolCalls);
                } else {
                    $expectingToolResult = false;
                    $pendingToolCallIds = [];
                }
            } elseif ($role === Role::Tool) {
                // 工具消息应该对应之前的工具调用
                if (! $expectingToolResult) {
                    $toolContent = $this->truncateContent($message->getContent());
                    $toolName = method_exists($message, 'getName') ? $message->getName() : 'unknown';
                    $toolCallId = method_exists($message, 'getToolCallId') ? $message->getToolCallId() : 'unknown';

                    $errorMsg = "Invalid message sequence: Found unexpected tool message at position {$index}.\n\n";

                    $errorMsg .= "Tool message details:\n";
                    $errorMsg .= "Tool name: {$toolName}\n";
                    $errorMsg .= "Tool call ID: {$toolCallId}\n";
                    $errorMsg .= "Content: \"{$toolContent}\"\n\n";

                    $errorMsg .= "Problem: This tool message appears without a preceding assistant message with tool_calls.\n";
                    $errorMsg .= 'Solution: Tool messages must be preceded by an assistant message that contains tool_calls.';

                    throw new LLMModelException($errorMsg);
                }

                // 检查工具调用ID是否匹配
                $toolCallId = method_exists($message, 'getToolCallId') ? $message->getToolCallId() : null;
                if ($toolCallId && in_array($toolCallId, $pendingToolCallIds)) {
                    // 移除已处理的工具调用ID
                    $pendingToolCallIds = array_diff($pendingToolCallIds, [$toolCallId]);
                } elseif ($toolCallId && ! in_array($toolCallId, $pendingToolCallIds)) {
                    // 工具调用ID不匹配的情况
                    $toolContent = $this->truncateContent($message->getContent());
                    $toolName = method_exists($message, 'getName') ? $message->getName() : 'unknown';

                    $errorMsg = "Invalid message sequence: Tool message ID mismatch at position {$index}.\n\n";

                    $errorMsg .= "Tool message details:\n";
                    $errorMsg .= "Tool name: {$toolName}\n";
                    $errorMsg .= "Tool call ID: {$toolCallId}\n";
                    $errorMsg .= "Content: \"{$toolContent}\"\n\n";

                    $errorMsg .= 'Expected tool_call IDs: ' . implode(', ', $pendingToolCallIds) . "\n";
                    $errorMsg .= "Found tool_call ID: {$toolCallId}\n\n";

                    $errorMsg .= 'Solution: Ensure tool message IDs match the tool_call IDs from the preceding assistant message.';

                    throw new LLMModelException($errorMsg);
                }

                // 如果所有工具调用都已处理，重置状态
                if (empty($pendingToolCallIds)) {
                    $expectingToolResult = false;
                }
            }

            $previousMessage = $message;
        }

        // 最后检查是否还有未处理的工具调用
        if ($expectingToolResult && ! empty($pendingToolCallIds)) {
            $errorMsg = "Invalid message sequence: Missing tool result messages for pending tool_calls.\n\n";

            $errorMsg .= 'Pending tool_call IDs: ' . implode(', ', $pendingToolCallIds) . "\n\n";

            $errorMsg .= "Problem: The conversation ends with unresolved tool_calls.\n";
            $errorMsg .= "Solution: Each tool_call must be followed by a corresponding tool message with matching ID.\n\n";

            $errorMsg .= "Expected sequence:\n";
            $errorMsg .= "1. Assistant message (with tool_calls)\n";
            $errorMsg .= "2. Tool message(s) (one for each tool_call ID)\n";
            $errorMsg .= '3. Assistant message (response based on tool results)';

            throw new LLMModelException($errorMsg);
        }
    }

    /**
     * 截断内容用于错误显示，避免日志过长.
     *
     * @param string $content 原始内容
     * @param int $maxLength 最大长度
     * @return string 截断后的内容
     */
    private function truncateContent(string $content, int $maxLength = 100): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        return mb_substr($content, 0, $maxLength - 3) . '...';
    }
}

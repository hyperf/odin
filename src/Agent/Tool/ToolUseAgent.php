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

namespace Hyperf\Odin\Agent\Tool;

use Closure;
use Generator;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Contract\Memory\MemoryInterface;
use Hyperf\Odin\Contract\Model\ModelInterface;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Utils\TimeUtil;
use Hyperf\Odin\Utils\ToolUtil;
use Psr\Log\LoggerInterface;
use Throwable;

class ToolUseAgent
{
    protected string $assistantEmptyContentPlaceholder = '';

    /**
     * 工具调用深度.
     */
    private int $toolsDepth = 30;

    /**
     * 已使用的工具记录.
     */
    private array $usedTools = [];

    /**
     * 工具调用前的回调函数.
     */
    private ?Closure $toolCallsBeforeEvent = null;

    /**
     * 工具调用重试计数.
     */
    private int $toolCallRetryCount = 0;

    /**
     * 最大重试次数.
     */
    private int $maxToolCallRetries = 3;

    private float $frequencyPenalty = 0.0;

    private float $presencePenalty = 0.0;

    private array $businessParams = [];

    private array $mcpTools = [];

    public function __construct(
        private readonly ModelInterface $model,
        private ?MemoryInterface $memory = null,
        private array $tools = [],
        private readonly float $temperature = 0.6,
        private readonly ?LoggerInterface $logger = null,
    ) {
        if ($this->memory === null) {
            $this->memory = new MemoryManager();
        }
        $this->tools = $this->formatTools($tools);
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

    /**
     * 流式聊天接口.
     */
    public function chatStreamed(?UserMessage $input = null): Generator
    {
        $gen = $this->call($input, stream: true);
        while ($gen->valid()) {
            /** @var ChatCompletionStreamResponse $response */
            $response = $gen->current();

            $toolCalls = [];
            $content = '';
            $lastChoice = null;
            /** @var ChatCompletionChoice $choice */
            foreach ($response->getStreamIterator() as $choice) {
                $lastChoice = $choice;
                $message = $choice->getMessage();
                $content .= $message->getContent();
                if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
                    foreach ($message->getToolCalls() as $toolCall) {
                        if ($toolCall->getId()) {
                            $toolCalls[] = new ToolCall($toolCall->getName(), [], $toolCall->getId(), $toolCall->getType(), $toolCall->getStreamArguments());
                        } else {
                            /** @var ToolCall $lastToolCall */
                            $lastToolCall = end($toolCalls);
                            $lastToolCall->appendStreamArguments($toolCall->getStreamArguments());
                        }
                    }
                    // 如果是工具，持续获取内容，直到工具内容完整
                    continue;
                }

                // 响应流式
                yield $choice;
            }
            // 切出一个换行
            if ($content !== '') {
                yield ChatCompletionChoice::fromArray([
                    'delta' => [
                        'content' => "\n",
                    ],
                ]);
            }

            $generatorSendMessage = null;

            // 检查完整流响应结束后是否存在工具调用失败的情况
            if ($lastChoice && $lastChoice->isFinishedByToolCall() && empty($toolCalls)) {
                // 流式响应中检测到工具调用失败情况
                $this->logger?->info('StreamedToolCallFailureDetected', [
                    'finish_reason' => $lastChoice->getFinishReason(),
                    'has_tool_calls' => ! empty($toolCalls),
                ]);

                // 增加重试计数
                ++$this->toolCallRetryCount;

                // 检查是否已达到最大重试次数
                if ($this->toolCallRetryCount >= $this->maxToolCallRetries) {
                    // 生成错误消息
                    $errorMessage = $this->getToolCallErrorMessage($content);
                    $errorAssistantMessage = new AssistantMessage($errorMessage);

                    // 记录日志
                    $this->logger?->warning('StreamMaxToolCallRetriesReached', [
                        'retry_count' => $this->toolCallRetryCount,
                        'max_retries' => $this->maxToolCallRetries,
                    ]);

                    // 添加到记忆
                    $this->memory->addMessage($errorAssistantMessage);

                    // 创建一个新的Choice供流式输出
                    yield new ChatCompletionChoice(
                        $errorAssistantMessage,
                        0,
                        null,
                        'stop'
                    );

                    // 不再继续调用生成器
                    break;
                }
                // 未达到最大重试次数，生成提示消息
                $promptMessage = $this->getRetryPromptByCount($this->toolCallRetryCount);

                $retryMessage = new UserMessage($promptMessage);
                $this->memory->addMessage($retryMessage);

                $this->logger?->info('StreamRetryingToolCall', [
                    'retry_count' => $this->toolCallRetryCount,
                    'max_retries' => $this->maxToolCallRetries,
                ]);

                // 不发送任何消息，使下一轮迭代重新请求模型
                $gen->send(null);
                continue;
            }

            if (! empty($toolCalls)) {
                // 如果有 toolsCall 但是 content 是空，自动加上
                if ($content === '') {
                    $content = $this->assistantEmptyContentPlaceholder;
                }
                $generatorSendMessage = new AssistantMessage($content, $toolCalls);
            }

            $gen->send($generatorSendMessage);
        }
        return $gen->getReturn();
    }

    /**
     * 标准聊天接口.
     */
    public function chat(?UserMessage $input = null): ChatCompletionResponse
    {
        $gen = $this->call($input);
        while ($gen->valid()) {
            $gen->next();
        }
        return $gen->getReturn();
    }

    /**
     * 设置工具调用前回调.
     */
    public function setToolCallBeforeEvent(Closure $callback): self
    {
        $this->toolCallsBeforeEvent = $callback;
        return $this;
    }

    /**
     * 设置工具调用深度.
     */
    public function setToolsDepth(int $depth): self
    {
        $this->toolsDepth = $depth;
        return $this;
    }

    public function getUsedTools(): array
    {
        return $this->usedTools;
    }

    public function getMcpTools(): array
    {
        return $this->mcpTools;
    }

    protected function call(?UserMessage $input = null, bool $stream = false): Generator
    {
        if ($input) {
            $this->memory->addMessage($input);
        }
        $depth = 0;
        // 重置重试计数
        $this->toolCallRetryCount = 0;

        while (true) {
            // 合并系统消息和普通消息
            $messages = array_merge($this->memory->getSystemMessages(), $this->memory->getMessages());

            if (! $stream) {
                $response = $this->model->chat(
                    messages: $messages,
                    temperature: $this->temperature,
                    tools: array_values($this->tools),
                    frequencyPenalty: $this->frequencyPenalty,
                    presencePenalty: $this->presencePenalty,
                    businessParams: $this->businessParams,
                );
            } else {
                $response = $this->model->chatStream(
                    messages: $messages,
                    temperature: $this->temperature,
                    tools: array_values($this->tools),
                    frequencyPenalty: $this->frequencyPenalty,
                    presencePenalty: $this->presencePenalty,
                    businessParams: $this->businessParams,
                );
            }
            /** @var null|AssistantMessage $assistantMessage */
            $assistantMessage = yield $response;

            if ($response instanceof ChatCompletionStreamResponse && is_null($assistantMessage)) {
                return $response;
            }
            if ($response instanceof ChatCompletionResponse && is_null($assistantMessage)) {
                $assistantMessage = $response->getFirstChoice()?->getMessage();
            }

            if (! $assistantMessage instanceof AssistantMessage) {
                // 如果不是AssistantMessage实例，直接返回响应
                return $response;
            }

            $this->memory->addMessage($assistantMessage);

            // 添加处理特殊情况的逻辑
            if ($response instanceof ChatCompletionResponse && $assistantMessage instanceof AssistantMessage) {
                $choice = $response->getFirstChoice();
                // 检查是否存在不一致：finish_reason 是 tool_calls 但没有实际的工具调用
                if ($choice?->isFinishedByToolCall() && ! $assistantMessage->hasToolCalls()) {
                    // 处理工具调用失败情况
                    $continueProcessing = $this->handleToolCallFailure($response, $assistantMessage, $choice);
                    if (! $continueProcessing) {
                        break;
                    }
                    continue;
                }
            }

            if ($assistantMessage->hasToolCalls()) {
                $toolResultMessages = $this->executeToolCalls($assistantMessage);
                foreach ($toolResultMessages as $toolMessage) {
                    $this->memory->addMessage($toolMessage);
                }
            }
            ++$depth;

            if ($this->shouldContinueToolCalls($depth, $assistantMessage)) {
                $this->logger?->debug('ContinueToolCall', [
                    'depth' => $depth,
                    'has_tool_calls' => $assistantMessage->hasToolCalls(),
                ]);
                if ($assistantMessage->getContent() === '') {
                    $assistantMessage->setContent($this->assistantEmptyContentPlaceholder);
                }
            } else {
                break;
            }
        }

        return $response;
    }

    /**
     * 根据重试次数获取相应的提示词.
     *
     * @param int $retryCount 当前重试次数
     * @return string 对应的提示词
     */
    protected function getRetryPromptByCount(int $retryCount): string
    {
        return match ($retryCount) {
            1 => '系统检测到您的响应表明需要进行工具调用(finish_reason为tool_calls)，但响应中未包含具体的工具调用参数。请明确提供您需要调用的工具名称及参数，确保工具调用格式正确。请直接重新提供完整的工具调用，无需解释原因。',
            2 => '系统再次检测到工具调用格式错误。请明确提供您需要调用的工具名称及参数，确保工具调用格式正确。请直接重新提供完整的工具调用，无需解释原因。',
            default => '系统多次检测到工具调用格式错误。请明确提供您需要调用的工具名称及参数，确保工具调用格式正确。请直接重新提供完整的工具调用，无需解释原因，这是您最后的尝试机会。',
        };
    }

    /**
     * 获取工具调用失败的错误消息.
     *
     * @param string $originalContent 原始消息内容
     * @return string 错误提示消息
     */
    protected function getToolCallErrorMessage(string $originalContent = ''): string
    {
        $errorMessage = '抱歉，我在尝试使用工具时遇到了问题。我原本打算使用工具来帮助您完成请求，但似乎我无法正确调用所需的工具。请您尝试重新描述您的需求，或者明确指出您希望我使用哪个工具以及需要提供哪些参数。我会尽力为您提供帮助。';

        // 如果原始消息有内容，则保留并附加错误信息
        if (! empty(trim($originalContent))) {
            $errorMessage = $originalContent . "\n\n" . $errorMessage;
        }

        return $errorMessage;
    }

    /**
     * @return array<ToolDefinition>
     */
    private function formatTools(array $tools): array
    {
        $definitionTools = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ToolDefinition) {
                $definitionTools[$tool->getName()] = $tool;
            }
            if ($tool instanceof ToolInterface) {
                $definitionTool = $tool->toToolDefinition();
                $definitionTools[$definitionTool->getName()] = $definitionTool;
            }
            if (is_array($tool)) {
                $definitionTool = ToolUtil::createFromArray($tool);
                if ($definitionTool) {
                    $definitionTools[$definitionTool->getName()] = $definitionTool;
                }
            }
        }
        foreach ($this->model->getMcpServerManager()?->getAllTools() ?? [] as $tool) {
            $definitionTools[$tool->getName()] = $tool;
            $this->mcpTools[$tool->getName()] = $tool;
        }
        return $definitionTools;
    }

    /**
     * 执行工具调用.
     * @return ToolMessage[]
     */
    private function executeToolCalls(AssistantMessage $message): array
    {
        $toolResults = [];
        $toolCalls = $message->getToolCalls();

        $this->logger?->info('AgentResponseToolCalls', [
            'tool_calls' => array_map(static fn ($toolCall) => $toolCall->toArray(), $toolCalls),
        ]);

        if ($this->toolCallsBeforeEvent) {
            call_user_func($this->toolCallsBeforeEvent, $toolCalls);
        }

        $toolParallel = new ToolExecutor();
        foreach ($toolCalls as $toolCall) {
            $tool = null;
            if ($toolCall->getName() === 'multi_tool_use.parallel') {
                $tool = new MultiToolUseParallelTool($this->tools);
            } else {
                $tool = $this->tools[$toolCall->getName()] ?? null;
            }
            if (! $tool) {
                continue;
            }

            $toolCallback = function () use ($tool, $toolCall, &$toolResults) {
                $start = microtime(true);
                $success = true;
                $throwable = null;
                try {
                    $callToolResult = call_user_func($tool->getToolHandler(), $toolCall->getArguments());
                    $this->logger?->debug('CallToolResultOrigin', [
                        'tool_id' => $toolCall->getId(),
                        'tool_name' => $tool->getName(),
                        'result_type' => gettype($callToolResult),
                        'result' => $callToolResult,
                    ]);
                } catch (Throwable $throwable) {
                    $success = false;
                    $this->logger?->warning('ErrorDuringToolCall', [
                        'tool_id' => $toolCall->getId(),
                        'tool_name' => $tool->getName(),
                        'arguments' => $toolCall->getArguments(),
                        'error_message' => $throwable->getMessage(),
                        'error_code' => $throwable->getCode(),
                        'error_file' => $throwable->getFile(),
                        'error_line' => $throwable->getLine(),
                        'error_trace' => $throwable->getTraceAsString(),
                    ]);
                    $callToolResult = 'error_during_tool_call | ' . json_encode([
                        'tool_id' => $toolCall->getId(),
                        'tool_name' => $tool->getName(),
                        'arguments' => $toolCall->getArguments(),
                        'error_message' => $throwable->getMessage(),
                    ], JSON_UNESCAPED_UNICODE);
                } finally {
                    $usedTool = new UsedTool(
                        elapsedTime: TimeUtil::calculateDurationMs($start, 2),
                        success: $success,
                        id: $toolCall->getId(),
                        name: $tool->getName(),
                        arguments: $toolCall->getArguments(),
                        result: $callToolResult,
                        errorMessage: $throwable?->getMessage() ?? '',
                    );
                    $this->usedTools[$toolCall->getId()] = $usedTool;
                    $this->logger?->info('ToolCallResult', $usedTool->toArray());
                }

                if (! is_null($callToolResult)) {
                    $callToolResult = $this->formatToolResult($callToolResult);
                    $toolResults[$toolCall->getId()] = new ToolMessage($callToolResult, $toolCall->getId(), $tool->getName(), $toolCall->getArguments());
                }
            };
            $toolParallel->add($toolCallback);
        }
        $toolParallel->run();

        return $toolResults;
    }

    /**
     * 判断是否继续工具调用.
     *
     * @param int $currentDepth 当前调用深度
     * @param AssistantMessage $lastMessage 最后一条助手消息
     * @return bool 是否继续调用
     */
    private function shouldContinueToolCalls(int $currentDepth, AssistantMessage $lastMessage): bool
    {
        // 超过最大深度限制
        if ($currentDepth >= $this->toolsDepth) {
            $this->logger?->info('StopToolCallReason', [
                'reason' => 'max_depth_reached',
                'current_depth' => $currentDepth,
                'max_depth' => $this->toolsDepth,
            ]);
            return false;
        }

        // 如果消息中没有工具调用，则不继续
        if (! $lastMessage->hasToolCalls()) {
            return false;
        }

        // 通过所有检查，允许继续调用
        return true;
    }

    /**
     * 格式化工具调用结果，使其适合AI模型处理.
     *
     * @param mixed $callToolResult 原始工具调用结果
     * @return string 格式化后的结果字符串
     */
    private function formatToolResult(mixed $callToolResult): string
    {
        // 处理数组结果
        if (is_array($callToolResult)) {
            try {
                return json_encode($callToolResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                return 'Array result could not be converted to JSON: ' . $e->getMessage();
            }
        }

        // 处理对象结果，转换为数组
        if (is_object($callToolResult)) {
            if (method_exists($callToolResult, 'toArray')) {
                // 如果对象有toArray方法，先转换为数组
                return $this->formatToolResult($callToolResult->toArray());
            }
            if (method_exists($callToolResult, '__toString')) {
                // 如果对象有__toString方法，转换为字符串
                return (string) $callToolResult;
            }
            // 尝试将对象序列化为JSON
            try {
                return json_encode($callToolResult, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                // 如果无法序列化为JSON，则使用对象的类名
                return 'Object of type (' . get_class($callToolResult) . ')';
            }
        }

        // 处理资源类型
        if (is_resource($callToolResult)) {
            return 'Resource of type (' . get_resource_type($callToolResult) . ')';
        }

        // 处理闭包
        if ($callToolResult instanceof Closure) {
            return 'Closure function';
        }

        // 处理布尔值
        if (is_bool($callToolResult)) {
            return $callToolResult ? 'true' : 'false';
        }

        // 处理数值和其他标量类型
        if (is_scalar($callToolResult) || is_null($callToolResult)) {
            return (string) $callToolResult;
        }

        // 其他未处理的类型
        return 'Unprocessable result of type ' . gettype($callToolResult);
    }

    /**
     * 处理工具调用失败情况.
     *
     * @param ChatCompletionResponse $response 响应对象
     * @param AssistantMessage $assistantMessage 助手消息
     * @param ChatCompletionChoice $choice 选择对象
     * @return bool 是否继续处理（true=继续，false=终止）
     */
    private function handleToolCallFailure(
        ChatCompletionResponse $response,
        AssistantMessage $assistantMessage,
        ChatCompletionChoice $choice
    ): bool {
        // 检查是否已达到最大重试次数
        if ($this->toolCallRetryCount >= $this->maxToolCallRetries) {
            return $this->handleMaxRetryReached($response, $assistantMessage, $choice);
        }

        // 增加重试计数
        ++$this->toolCallRetryCount;

        // 根据重试次数获取提示词
        $promptMessage = $this->getRetryPromptByCount($this->toolCallRetryCount);

        // 添加一条用户消息说明情况
        $retryMessage = new UserMessage($promptMessage);
        $this->memory->addMessage($retryMessage);

        // 记录重试信息
        $this->logger?->info('RetryingToolCall', [
            'retry_count' => $this->toolCallRetryCount,
            'max_retries' => $this->maxToolCallRetries,
        ]);

        // 返回true表示继续处理
        return true;
    }

    /**
     * 处理达到最大重试次数的情况.
     *
     * @param ChatCompletionResponse $response 响应对象
     * @param AssistantMessage $assistantMessage 助手消息
     * @param ChatCompletionChoice $choice 选择对象
     * @return bool 是否继续处理（false=终止）
     */
    private function handleMaxRetryReached(
        ChatCompletionResponse $response,
        AssistantMessage $assistantMessage,
        ChatCompletionChoice $choice
    ): bool {
        $this->logger?->warning('MaxToolCallRetriesReached', [
            'retry_count' => $this->toolCallRetryCount,
            'max_retries' => $this->maxToolCallRetries,
        ]);

        // 生成新的包含错误信息的AssistantMessage
        $errorMessage = $this->getToolCallErrorMessage($assistantMessage->getContent());

        // 创建新的AssistantMessage和Choice
        $newAssistantMessage = new AssistantMessage($errorMessage);
        $this->memory->addMessage($newAssistantMessage);

        // 创建新的响应
        $newChoice = new ChatCompletionChoice(
            $newAssistantMessage,
            $choice->getIndex(),
            $choice->getLogprobs(),
            'stop'  // 使用stop作为结束原因
        );

        // 更新响应的选择列表
        $response->setChoices([$newChoice]);

        // 返回false表示终止处理
        return false;
    }
}

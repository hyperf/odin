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
use Hyperf\Odin\Utils\ToolUtil;
use Psr\Log\LoggerInterface;
use Throwable;

class ToolUseAgent
{
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

    private float $frequencyPenalty = 0.0;

    private float $presencePenalty = 0.0;

    private array $businessParams = [];

    public function __construct(
        private ModelInterface $model,
        private ?MemoryInterface $memory = null,
        private array $tools = [],
        private float $temperature = 0.6,
        private ?LoggerInterface $logger = null,
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
            /** @var ChatCompletionChoice $choice */
            foreach ($response->getStreamIterator() as $choice) {
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
            $generatorSendMessage = null;
            if (! empty($toolCalls)) {
                // 如果有 toolsCall 但是 content 是空，自动加上
                if ($content === '') {
                    $content = 'tool_call';
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

    protected function call(?UserMessage $input = null, bool $stream = false): Generator
    {
        if ($input) {
            $this->memory->addMessage($input);
        }
        $depth = 0;

        while (true) {
            // 合并系统消息和普通消息
            $systemMessages = $this->memory->getSystemMessages();
            if (! empty($systemMessages)) {
                $messages = array_merge([end($systemMessages)], $this->memory->getMessages());
            } else {
                $messages = $this->memory->getMessages();
            }

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
                $assistantMessage = $response->getFirstChoice()->getMessage();
            }

            if (! $assistantMessage instanceof AssistantMessage) {
                // 如果不是AssistantMessage实例，直接返回响应
                return $response;
            }
            $this->memory->addMessage($assistantMessage);
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
            } else {
                break;
            }
        }

        return $response;
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
                        elapsedTime: round((microtime(true) - $start) * 1000, 2),
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
}

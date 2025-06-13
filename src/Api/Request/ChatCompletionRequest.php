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
}

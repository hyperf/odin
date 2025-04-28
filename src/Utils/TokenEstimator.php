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

namespace Hyperf\Odin\Utils;

use Hyperf\Odin\Contract\Message\MessageInterface;
use Hyperf\Odin\Contract\Tool\ToolInterface;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;

/**
 * 提供高效简单的 Token 估算功能，使用启发式算法实现。
 * 基于字符串长度和单词数量进行估算，快速获得近似值。
 */
class TokenEstimator
{
    /**
     * 中文字符和英文单词平均 token 比例.
     */
    private const CHINESE_CHAR_RATIO = 1.5;

    private const ENGLISH_WORD_RATIO = 0.75;

    /**
     * 每个单词的平均长度.
     */
    private const AVG_WORD_LENGTH = 5.1;

    /**
     * 各类消息的基础开销.
     */
    private int $messageBaseOverhead = 8;

    /**
     * 工具调用的基础开销系数.
     * 工具定义通常会有结构化的JSON模式，在API传输中会有额外的开销
     */
    private float $toolDefinitionOverheadFactor = 3.7;

    /**
     * 系统上下文的增加因子
     * 系统上下文通常会有额外的指令和处理逻辑.
     */
    private float $systemContextFactor = 1.3;

    /**
     * 当前使用的模型名称.
     */
    private string $model;

    /**
     * TokenEstimator构造函数.
     *
     * @param string $model 模型名称，不影响当前估算算法，但保留兼容性
     * @param int $messageBaseOverhead 消息基础开销
     * @param float $toolDefinitionOverheadFactor 工具定义开销系数
     * @param float $systemContextFactor 系统上下文因子
     */
    public function __construct(
        string $model = 'gpt-4',
        int $messageBaseOverhead = 8,
        float $toolDefinitionOverheadFactor = 3.7,
        float $systemContextFactor = 1.3
    ) {
        $this->model = $model;
        $this->messageBaseOverhead = $messageBaseOverhead;
        $this->toolDefinitionOverheadFactor = $toolDefinitionOverheadFactor;
        $this->systemContextFactor = $systemContextFactor;
    }

    /**
     * 估算给定文本字符串中的 Token 数量.
     *
     * @param string $text 需要估算 Token 的文本
     * @return int 估算的 Token 数量
     */
    public function estimateTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // 计算中文字符数量
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $text);

        // 计算非中文部分的单词数量（简化处理）
        $nonChineseText = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $text);
        $wordCount = str_word_count($nonChineseText) ?: strlen($nonChineseText) / self::AVG_WORD_LENGTH;

        // 根据中文字符和英文单词计算token
        $tokens = ($chineseCount * self::CHINESE_CHAR_RATIO) + ($wordCount * self::ENGLISH_WORD_RATIO);

        // 处理标点符号和空格等
        $tokens += substr_count($text, ' ') * 0.25;
        $tokens += preg_match_all('/[.,:;!?]/', $text) * 0.3;

        return (int) max(1, round($tokens));
    }

    /**
     * 估算消息对象的 Token 数量
     * 包含消息结构开销
     *
     * @param MessageInterface $message 消息对象
     * @return int 估算的消息 Token 数量
     */
    public function estimateMessageTokens(MessageInterface $message): int
    {
        // 基础消息内容的token
        $contentTokens = 0;

        // 处理多部分内容的用户消息（如包含图片）
        if ($message instanceof UserMessage && ($contents = $message->getContents()) !== null) {
            foreach ($contents as $content) {
                if ($content->getType() === 'text') {
                    $contentTokens += $this->estimateTokens($content->getText() ?? '');
                } elseif ($content->getType() === 'image_url') {
                    // 图片token估算 - 简化处理
                    $contentTokens += 65; // 低分辨率图片约65 tokens
                }
            }
        } else {
            // 常规文本消息
            $contentTokens = $this->estimateTokens($message->getContent() ?? '');
        }

        // 处理助手消息中的工具调用
        $toolCallsTokens = 0;
        if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
            $toolCalls = $message->getToolCalls();
            foreach ($toolCalls as $toolCall) {
                // 工具名称tokens
                $toolCallsTokens += $this->estimateTokens($toolCall->getName());

                // 参数tokens (将参数转为JSON字符串计算)
                $argsJson = $toolCall->getSerializedArguments();
                $toolCallsTokens += $this->estimateTokens($argsJson);

                // 工具调用ID tokens
                $toolCallsTokens += $this->estimateTokens($toolCall->getId());

                // 每个工具调用的额外开销
                $toolCallsTokens += 8;
            }
        }

        // 消息格式开销
        $roleTokens = $this->estimateTokens((string) $message->getRole()->value);

        // 基础开销
        $baseOverhead = $this->messageBaseOverhead;

        // 系统消息通常会有更多的上下文处理开销
        if ($message->getRole()->value === 'system') {
            $baseOverhead = (int) round($baseOverhead * $this->systemContextFactor);
        }

        return (int) ($contentTokens + $roleTokens + $toolCallsTokens + $baseOverhead);
    }

    /**
     * 估算工具定义的 Token 数量.
     *
     * @param array $tools 工具定义数组
     * @return int 估算的工具定义 Token 数量
     */
    public function estimateToolsTokens(array $tools): int
    {
        if (empty($tools)) {
            return 0;
        }

        // 将工具转换为数组
        $definitions = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $definitions[] = $tool->toToolDefinition()->toArray();
            } elseif ($tool instanceof ToolDefinition) {
                $definitions[] = $tool->toArray();
            } elseif (is_array($tool) && isset($tool['name'], $tool['description'])) {
                $definitions[] = $tool;
            }
        }

        // 直接计算 JSON 字符串的近似 token 数量
        $jsonString = json_encode(['tools' => $definitions], JSON_UNESCAPED_UNICODE);
        $baseTokens = $this->estimateTokens($jsonString);

        // 工具定义通常在API传输中会被转换成更复杂的结构，这里应用额外系数
        $toolTokens = (int) round($baseTokens * $this->toolDefinitionOverheadFactor);

        // 如果工具多于2个，每增加一个工具额外增加一定开销
        if (count($tools) > 2) {
            $toolTokens += (count($tools) - 2) * 20;
        }

        return $toolTokens;
    }

    /**
     * 获取当前使用的模型名称.
     *
     * @return string 模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }
}

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

namespace HyperfTest\Odin\ModelTest\TestRunners;

use GuzzleHttp\Exception\ConnectException;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\ModelMapper;
use HyperfTest\Odin\ModelTest\Formatters\MarkdownFormatter;
use HyperfTest\Odin\ModelTest\Utils\TestUtils;
use RuntimeException;
use Throwable;

class StreamTestRunner
{
    /**
     * @var ModelMapper 模型映射器
     */
    private ModelMapper $modelMapper;

    /**
     * @var MarkdownFormatter Markdown格式化工具
     */
    private MarkdownFormatter $formatter;

    /**
     * @var int 超时时间 (秒)
     */
    private int $timeoutSeconds = 600;

    /**
     * @var array 特定模型的超时设置 (秒)
     */
    private array $modelTimeouts = [
        'deepseek-r1' => 900,    // 15分钟
        'deepseek-v3' => 900,    // 15分钟
        'Doubao-pro-32k' => 900, // 15分钟
        'doubao-1.5-vision-pro-32k' => 900, // 15分钟
    ];

    /**
     * @var int 最大迭代次数（防止死循环）
     */
    private int $maxIterations = 10000;

    /**
     * @var int 超时错误重试次数
     */
    private int $maxRetries = 2;

    /**
     * @var array 测试失败记录
     */
    private array $failedModels = [];

    /**
     * @var array 测试结果摘要
     */
    private array $resultSummary = [];

    public function __construct(ModelMapper $modelMapper, MarkdownFormatter $formatter)
    {
        $this->modelMapper = $modelMapper;
        $this->formatter = $formatter;
    }

    /**
     * 测试单个模型的单个问题.
     */
    public function testSingleQuestion(string $modelName, array $questionData): array
    {
        // 获取此模型的超时时间
        $modelTimeout = $this->getModelTimeout($modelName);

        // 设置脚本执行时间
        set_time_limit($modelTimeout + 30);

        $retries = 0;
        $lastException = null;

        // 添加重试逻辑
        while ($retries <= $this->maxRetries) {
            try {
                $model = $this->modelMapper->getChatModel($modelName);
                $messages = [
                    new SystemMessage(''),
                    new UserMessage($questionData['question']),
                ];

                $start = microtime(true);
                $response = $model->chatStream($messages);

                $reasoningEnd = false;
                $reasoningContent = '';
                $finalContent = '';

                echo '【思考中...】' . PHP_EOL;

                $iterationCount = 0;

                foreach ($response->getStreamIterator() as $choice) {
                    ++$iterationCount;
                    if ($iterationCount > $this->maxIterations) {
                        throw new RuntimeException('迭代次数超过限制，可能陷入循环中');
                    }

                    /** @var AssistantMessage $message */
                    $message = $choice->getMessage();

                    if (! $reasoningEnd && ! $message->hasReasoningContent()) {
                        echo PHP_EOL . '【思考结束】' . PHP_EOL;
                        $reasoningEnd = true;
                    }

                    if ($message->getReasoningContent()) {
                        $reasoningContent .= $message->getReasoningContent();
                        echo $message->getReasoningContent();
                    } else {
                        $finalContent .= $message->getContent();
                        echo $message->getContent();
                    }

                    // 超时检查
                    if ((microtime(true) - $start) > $modelTimeout) {
                        throw new RuntimeException("响应超时，已超过 {$modelTimeout} 秒");
                    }
                }

                // 计算完整响应时间和token数
                $responseTime = round(microtime(true) - $start, 2);

                // 计算大致的token数量
                $estimatedTokens = TestUtils::estimateTokens($finalContent);
                $reasoningTokens = TestUtils::estimateTokens($reasoningContent);
                $totalTokens = $estimatedTokens + $reasoningTokens;

                // 思考过程比例计算
                $reasoningRatio = empty($reasoningContent) ? 0
                    : round(mb_strlen($reasoningContent) / (mb_strlen($reasoningContent) + mb_strlen($finalContent)) * 100);

                // 计算得分
                $normalizedScore = TestUtils::calculateScore($responseTime, $estimatedTokens, $questionData);

                return [
                    'response_time' => $responseTime,
                    'estimated_tokens' => $estimatedTokens,
                    'reasoning_tokens' => $reasoningTokens,
                    'total_tokens' => $totalTokens,
                    'reasoning_ratio' => $reasoningRatio,
                    'token_ratio' => $estimatedTokens / $questionData['expected_tokens'],
                    'content' => $finalContent,
                    'reasoning_content' => $reasoningContent,
                    'status' => 'success',
                    'score' => $normalizedScore,
                    'type' => $questionData['type'],
                    'iterations' => $iterationCount,
                ];
            } catch (Throwable $e) {
                $lastException = $e;

                // 仅对超时异常进行重试
                $isTimeout = (strpos($e->getMessage(), 'timed out') !== false)
                             || (strpos($e->getMessage(), 'timeout') !== false)
                             || (strpos($e->getMessage(), '超过') !== false)
                             || ($e instanceof RuntimeException && strpos($e->getMessage(), '响应超时') !== false)
                             || ($e instanceof ConnectException);

                if (! $isTimeout) {
                    // 非超时异常直接抛出
                    throw $e;
                }

                ++$retries;
                if ($retries <= $this->maxRetries) {
                    echo PHP_EOL . "⚠️ 模型 {$modelName} 在处理问题 \"{$questionData['type']}\" 时超时，正在进行第 {$retries} 次重试..." . PHP_EOL;
                    // 增加10%的超时时间进行重试
                    $modelTimeout = intval($modelTimeout * 1.1);
                    set_time_limit($modelTimeout + 30);
                    // 等待一秒后重试
                    sleep(1);
                }
            }
        }

        // 所有重试都失败，抛出最后一个异常
        throw $lastException;
    }

    /**
     * 测试所有模型.
     */
    public function testAllModels(array $testQuestions): array
    {
        $modelCount = count($this->modelMapper->getModels('chat'));
        $currentModel = 0;
        $this->resultSummary = [];
        $this->failedModels = [];

        $totalTestStartTime = microtime(true);

        // 测试每个模型
        foreach ($this->modelMapper->getModels('chat') as $modelName => $modelObject) {
            ++$currentModel;

            echo PHP_EOL . TestUtils::getSeparator() . PHP_EOL;
            echo "【模型 {$currentModel}/{$modelCount}】: " . $modelName . PHP_EOL;
            echo TestUtils::getStarLine() . PHP_EOL;

            try {
                // 写入模型标题
                $this->formatter->writeModelHeader($modelName, $currentModel, $modelCount, $testQuestions);

                $this->resultSummary[$modelName] = [
                    'model' => $modelName,
                    'questions' => [],
                    'total_time' => 0,
                    'total_tokens' => 0,
                    'avg_response_time' => 0,
                    'avg_tokens' => 0,
                    'success_count' => 0,
                    'error_count' => 0,
                    'performance_score' => 0,
                ];

                // 测试每个问题
                foreach ($testQuestions as $questionKey => $questionData) {
                    // 写入问题标题
                    $this->formatter->writeQuestionHeader($modelName, $questionData);

                    try {
                        echo PHP_EOL . '📝 测试类型: ' . $questionData['type'] . PHP_EOL;
                        echo '💬 用户: ' . $questionData['question'] . PHP_EOL;
                        echo TestUtils::getThinSeparator() . PHP_EOL;
                        echo '🤖 助手: ';

                        // 执行测试
                        $result = $this->testSingleQuestion($modelName, $questionData);

                        echo PHP_EOL . PHP_EOL;
                        echo '⏱️ 耗时: ' . $result['response_time'] . ' 秒' . PHP_EOL;

                        // 更新结果摘要
                        $this->resultSummary[$modelName]['questions'][$questionKey] = $result;
                        $this->resultSummary[$modelName]['total_time'] += $result['response_time'];
                        $this->resultSummary[$modelName]['total_tokens'] += $result['estimated_tokens'];
                        ++$this->resultSummary[$modelName]['success_count'];

                        // 准备思考过程数据
                        $reasoningData = [
                            'content' => $result['reasoning_content'],
                            'tokens' => $result['reasoning_tokens'],
                            'ratio' => $result['reasoning_ratio'],
                        ];

                        // 写入测试结果
                        $this->formatter->writeTestResult(
                            $result['response_time'],
                            $result['estimated_tokens'],
                            $result['score'],
                            $result['content'],
                            $questionData,
                            $reasoningData
                        );

                        echo "📝 已写入问题 \"{$questionData['type']}\" 的测试结果" . PHP_EOL;
                    } catch (Throwable $e) {
                        // 处理问题测试异常
                        $errorMessage = "【错误】模型 {$modelName} 处理问题 \"{$questionData['type']}\" 时出错: " . $e->getMessage();
                        $errorDetails = '异常类型: ' . get_class($e) . "\n文件: " . $e->getFile() . "\n行数: " . $e->getLine() . "\n堆栈跟踪:\n" . $e->getTraceAsString();

                        echo PHP_EOL . '❌ ' . $errorMessage . PHP_EOL;

                        // 更新摘要
                        $this->resultSummary[$modelName]['questions'][$questionKey] = [
                            'response_time' => 0,
                            'estimated_tokens' => 0,
                            'token_ratio' => 0,
                            'status' => 'error',
                            'error_message' => $e->getMessage(),
                            'score' => 0,
                            'type' => $questionData['type'],
                        ];
                        ++$this->resultSummary[$modelName]['error_count'];

                        // 记录错误
                        $this->formatter->writeErrorInfo($errorMessage, $errorDetails);
                        $this->failedModels[] = [
                            'model' => $modelName,
                            'question_type' => $questionData['type'],
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                // 计算平均值和总体性能得分
                $this->calculateModelSummary($modelName);

                // 写入模型总结
                $this->formatter->writeModelSummary($this->resultSummary[$modelName]);
                echo "📊 已完成模型 {$modelName} 的测试并写入总结" . PHP_EOL;
            } catch (Throwable $e) {
                // 处理模型级别异常
                $errorMessage = "【严重错误】模型 {$modelName} 初始化或处理过程中出错: " . $e->getMessage();
                $errorDetails = '异常类型: ' . get_class($e) . "\n文件: " . $e->getFile() . "\n行数: " . $e->getLine() . "\n堆栈跟踪:\n" . $e->getTraceAsString();

                echo PHP_EOL . '❌❌ ' . $errorMessage . PHP_EOL;

                // 记录错误
                $this->formatter->writeModelError($modelName, $currentModel, $errorMessage, $errorDetails);
                $this->failedModels[] = [
                    'model' => $modelName,
                    'question_type' => 'ALL',
                    'error' => $e->getMessage(),
                ];

                // 创建空的结果摘要条目
                $this->resultSummary[$modelName] = [
                    'model' => $modelName,
                    'questions' => [],
                    'total_time' => 0,
                    'total_tokens' => 0,
                    'avg_response_time' => 0,
                    'avg_tokens' => 0,
                    'success_count' => 0,
                    'error_count' => count($testQuestions),
                    'performance_score' => 0,
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                ];
            }

            echo TestUtils::getSeparator() . PHP_EOL;
        }

        $totalTestTime = round(microtime(true) - $totalTestStartTime, 2);

        // 完成报告
        $this->formatter->finalizeReport($this->resultSummary, $this->failedModels, $totalTestTime);

        echo PHP_EOL . '✅ 所有模型测试完成！' . PHP_EOL;
        if (! empty($this->failedModels)) {
            echo '⚠️ 有 ' . count($this->failedModels) . ' 个测试出现错误' . PHP_EOL;
        }
        echo "⏱️ 总测试时长: {$totalTestTime} 秒" . PHP_EOL;

        return [
            'summary' => $this->resultSummary,
            'failed' => $this->failedModels,
            'total_time' => $totalTestTime,
        ];
    }

    /**
     * 获取模型的超时时间.
     */
    private function getModelTimeout(string $modelName): int
    {
        return $this->modelTimeouts[$modelName] ?? $this->timeoutSeconds;
    }

    /**
     * 计算模型总结数据.
     */
    private function calculateModelSummary(string $modelName): void
    {
        if ($this->resultSummary[$modelName]['success_count'] > 0) {
            $this->resultSummary[$modelName]['avg_response_time']
                = round($this->resultSummary[$modelName]['total_time'] / $this->resultSummary[$modelName]['success_count'], 2);
            $this->resultSummary[$modelName]['avg_tokens']
                = round($this->resultSummary[$modelName]['total_tokens'] / $this->resultSummary[$modelName]['success_count']);
        }

        // 计算总体性能得分
        $totalScore = 0;
        $scoreCount = 0;
        foreach ($this->resultSummary[$modelName]['questions'] as $qResult) {
            if (isset($qResult['score']) && $qResult['score'] > 0) {
                $totalScore += $qResult['score'];
                ++$scoreCount;
            }
        }

        if ($scoreCount > 0) {
            $this->resultSummary[$modelName]['performance_score'] = round($totalScore / $scoreCount, 2);
        }
    }
}

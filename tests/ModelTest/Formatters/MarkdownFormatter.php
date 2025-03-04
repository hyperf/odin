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

namespace HyperfTest\Odin\ModelTest\Formatters;

use HyperfTest\Odin\ModelTest\Utils\TestUtils;

class MarkdownFormatter
{
    /**
     * @var string Markdown文件路径
     */
    private string $markdownFile;

    /**
     * @var string 测试模式 (stream/non-stream)
     */
    private string $testMode;

    public function __construct(string $markdownFile, string $testMode = 'non-stream')
    {
        $this->markdownFile = $markdownFile;
        $this->testMode = $testMode;
    }

    /**
     * 初始化Markdown报告头部.
     */
    public function initMarkdownReport(array $testQuestions): void
    {
        $markdownContent = '# 模型对比测试报告' . ($this->testMode === 'stream' ? ' (流式)' : ' (非流式)') . "\n\n";
        $markdownContent .= '- **测试时间**: ' . date('Y-m-d H:i:s') . "\n";
        $markdownContent .= '- **测试环境**: PHP ' . PHP_VERSION . "\n";
        $markdownContent .= '- **测试模式**: ' . ($this->testMode === 'stream' ? '流式聊天接口' : '非流式聊天接口 (同步)') . "\n\n";

        // 添加摘要表
        $markdownContent .= "## 测试摘要\n\n";
        $markdownContent .= "此表按照每个模型的平均表现排序（响应时间、token数量和成功率的综合评分）\n\n";
        $markdownContent .= "| 排名 | 模型 | 平均响应时间 | 平均token数 | 成功率 | 综合得分 |\n";
        $markdownContent .= "|:----:|:-----|:----------:|:----------:|:------:|:--------:|\n";
        $markdownContent .= "| - | *待测试完成后生成* | - | - | - | - |\n\n";

        // 添加测试问题概述
        $markdownContent .= "## 测试问题\n\n";
        $markdownContent .= "| 类型 | 问题 | 复杂度 | 类别 |\n";
        $markdownContent .= "|:-----|:-----|:------:|:------|\n";

        foreach ($testQuestions as $key => $questionData) {
            $markdownContent .= "| {$questionData['type']} | " . substr($questionData['question'], 0, 50) . (strlen($questionData['question']) > 50 ? '...' : '')
                . ' | ' . str_repeat('★', $questionData['complexity']) . " | {$questionData['category']} |\n";
        }

        $markdownContent .= "\n## 详细测试结果\n\n";

        // 写入文件头部
        file_put_contents($this->markdownFile, $markdownContent);
    }

    /**
     * 写入模型标题和单步调试命令.
     */
    public function writeModelHeader(string $modelName, int $currentModel, int $modelCount, array $testQuestions): void
    {
        $markdownModelId = TestUtils::getMarkdownId($modelName);
        $markdownModelContent = "<a id=\"model-{$markdownModelId}\"></a>\n";
        $markdownModelContent .= "### {$currentModel}. 模型: {$modelName}\n\n";

        // 添加单步调试命令
        $markdownModelContent .= "**单步调试命令**:\n\n";
        $markdownModelContent .= "```bash\n";
        foreach ($testQuestions as $qKey => $qData) {
            $markdownModelContent .= "# 测试 {$modelName} 的 {$qData['type']}\n";
            $scriptName = $this->testMode === 'stream' ? 'model-test-one' : 'model-test-one';
            $markdownModelContent .= "php tests/bin/{$scriptName}.php -m \"{$modelName}\" -q {$qKey}" . ($this->testMode === 'stream' ? ' -s' : '') . "\n\n";
        }
        $markdownModelContent .= "```\n\n";

        // 追加写入模型标题
        file_put_contents($this->markdownFile, $markdownModelContent, FILE_APPEND);
    }

    /**
     * 写入问题标题和详情.
     */
    public function writeQuestionHeader(string $modelName, array $questionData): void
    {
        $markdownModelId = TestUtils::getMarkdownId($modelName);
        $markdownQuestionId = TestUtils::getMarkdownId($questionData['type']);
        $markdownQuestionContent = "<a id=\"model-{$markdownModelId}-{$markdownQuestionId}\"></a>\n";
        $markdownQuestionContent .= "#### {$questionData['type']}\n\n";
        $markdownQuestionContent .= "**问题**: {$questionData['question']}  \n";
        $markdownQuestionContent .= '**复杂度**: ' . str_repeat('★', $questionData['complexity']) . "  \n";
        $markdownQuestionContent .= "**类别**: {$questionData['category']}  \n";
        $markdownQuestionContent .= "**描述**: {$questionData['description']}  \n\n";

        // 追加写入问题标题和详情
        file_put_contents($this->markdownFile, $markdownQuestionContent, FILE_APPEND);
    }

    /**
     * 写入测试结果详情.
     */
    public function writeTestResult(float $responseTime, int $estimatedTokens, float $normalizedScore, string $content, array $questionData, ?array $reasoningData = null): void
    {
        $tokenRatio = $estimatedTokens / $questionData['expected_tokens'];
        $tokenRatioFormatted = round($tokenRatio * 100) . '%';
        $performanceRating = TestUtils::getPerformanceRating($normalizedScore);

        // 准备详细结果的Markdown内容
        $markdownDetailContent = "**性能指标**:  \n";
        $markdownDetailContent .= "- 响应时间: {$responseTime} 秒  \n";
        $markdownDetailContent .= "- 估计token数: {$estimatedTokens} (预期的 {$tokenRatioFormatted})  \n";

        // 如果有思考过程信息（流式模式）
        if ($reasoningData !== null && isset($reasoningData['content'], $reasoningData['tokens'], $reasoningData['ratio'])) {
            $markdownDetailContent .= "- 思考过程token数: {$reasoningData['tokens']}  \n";
            $markdownDetailContent .= "- 思考/输出比例: {$reasoningData['ratio']}%  \n";
            $markdownDetailContent .= '- 总token数: ' . ($estimatedTokens + $reasoningData['tokens']) . "  \n";
        }

        $markdownDetailContent .= "- 性能得分: {$normalizedScore}/10 - {$performanceRating}  \n\n";

        $markdownDetailContent .= "**回答**:\n\n" . $content . "\n\n";

        // 如果有思考过程内容且不为空，添加思考过程部分
        if ($reasoningData !== null && ! empty($reasoningData['content'])) {
            $markdownDetailContent .= "**思考过程**:\n\n```\n" . $reasoningData['content'] . "\n```\n\n";
        }

        $markdownDetailContent .= "---\n\n";

        // 追加写入详细结果
        file_put_contents($this->markdownFile, $markdownDetailContent, FILE_APPEND);
    }

    /**
     * 写入错误信息.
     */
    public function writeErrorInfo(string $errorMessage, string $errorDetails): void
    {
        $markdownErrorContent = "**异常信息**:\n\n";
        $markdownErrorContent .= "```\n" . $errorMessage . "\n" . $errorDetails . "\n```\n\n";
        $markdownErrorContent .= "---\n\n";
        file_put_contents($this->markdownFile, $markdownErrorContent, FILE_APPEND);
    }

    /**
     * 写入模型级别的错误信息.
     */
    public function writeModelError(string $modelName, int $currentModel, string $errorMessage, string $errorDetails): void
    {
        $markdownModelErrorContent = '<a id="model-' . TestUtils::getMarkdownId($modelName) . "\"></a>\n";
        $markdownModelErrorContent .= "### {$currentModel}. 模型: {$modelName}\n\n";
        $markdownModelErrorContent .= "**严重错误**:\n\n";
        $markdownModelErrorContent .= "```\n" . $errorMessage . "\n" . $errorDetails . "\n```\n\n";
        $markdownModelErrorContent .= "该模型无法正常测试，已跳过。\n\n";
        $markdownModelErrorContent .= "---\n\n";
        file_put_contents($this->markdownFile, $markdownModelErrorContent, FILE_APPEND);
    }

    /**
     * 写入模型总结.
     */
    public function writeModelSummary(array $modelSummary): void
    {
        $markdownSummaryContent = "\n**模型总结**:\n\n";
        $markdownSummaryContent .= "- 平均响应时间: {$modelSummary['avg_response_time']} 秒\n";
        $markdownSummaryContent .= "- 平均Token数: {$modelSummary['avg_tokens']}\n";
        $markdownSummaryContent .= '- 成功率: ' . $modelSummary['success_count'] . '/'
            . ($modelSummary['success_count'] + $modelSummary['error_count']) . "\n";
        $markdownSummaryContent .= "- 综合性能得分: {$modelSummary['performance_score']}/10\n\n";
        $markdownSummaryContent .= "---\n\n";

        // 追加写入模型总结
        file_put_contents($this->markdownFile, $markdownSummaryContent, FILE_APPEND);
    }

    /**
     * 更新并完成测试报告.
     */
    public function finalizeReport(array $resultSummary, array $failedModels, float $totalTestTime): void
    {
        // 按性能得分对模型进行排序
        uasort($resultSummary, function ($a, $b) {
            return $b['performance_score'] <=> $a['performance_score'];
        });

        // 准备排序后的摘要表格
        $markdownSummaryTable = "## 测试摘要\n\n";
        $markdownSummaryTable .= "按综合性能得分排序（包含响应时间、token数量、成功率和回答质量）\n\n";
        $markdownSummaryTable .= "| 排名 | 模型 | 平均响应时间 | 平均token数 | 成功率 | 综合得分 |\n";
        $markdownSummaryTable .= "|:----:|:-----|:----------:|:----------:|:------:|:--------:|\n";

        $rank = 1;
        foreach ($resultSummary as $modelName => $data) {
            $successRate = $data['success_count'] . '/' . ($data['success_count'] + $data['error_count']);
            $successPercent = ($data['success_count'] + $data['error_count']) > 0
                ? round(($data['success_count'] / ($data['success_count'] + $data['error_count'])) * 100) . '%' : '0%';

            $markdownSummaryTable .= "| {$rank} | {$modelName} | {$data['avg_response_time']}秒 | {$data['avg_tokens']} | {$successRate} ({$successPercent}) | **{$data['performance_score']}/10** |\n";
            ++$rank;
        }

        // 准备问题类型比较表格
        $markdownQuestionsComparison = "\n## 问题类型对比\n\n";
        $testQuestions = [];
        foreach ($resultSummary as $modelData) {
            if (! empty($modelData['questions'])) {
                $testQuestions = array_keys($modelData['questions']);
                break;
            }
        }

        if (! empty($testQuestions)) {
            foreach ($testQuestions as $questionKey) {
                $questionType = '';
                foreach ($resultSummary as $modelName => $modelData) {
                    if (isset($modelData['questions'][$questionKey], $modelData['questions'][$questionKey]['type'])) {
                        $questionType = $modelData['questions'][$questionKey]['type'];
                        break;
                    }
                }

                if (! $questionType) {
                    continue;
                }

                $markdownQuestionsComparison .= "### {$questionType}\n\n";
                $markdownQuestionsComparison .= "| 模型 | 响应时间 | Token数 | 得分 |\n";
                $markdownQuestionsComparison .= "|:-----|:--------:|:------:|:-----:|\n";

                // 按性能得分对该问题的模型结果排序
                $questionResults = [];
                foreach ($resultSummary as $modelName => $modelData) {
                    if (isset($modelData['questions'][$questionKey])) {
                        $questionResults[$modelName] = $modelData['questions'][$questionKey];
                    }
                }

                uasort($questionResults, function ($a, $b) {
                    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
                });

                foreach ($questionResults as $modelName => $qData) {
                    if (($qData['status'] ?? '') === 'success') {
                        $markdownQuestionsComparison .= "| {$modelName} | {$qData['response_time']}秒 | {$qData['estimated_tokens']} | **{$qData['score']}/10** |\n";
                    } else {
                        $markdownQuestionsComparison .= "| {$modelName} | - | - | ❌ 错误 |\n";
                    }
                }

                $markdownQuestionsComparison .= "\n";
            }
        }

        // 替换摘要表格
        $fullMarkdown = file_get_contents($this->markdownFile);
        $fullMarkdown = preg_replace('/## 测试摘要.*?(?=##)/s', $markdownSummaryTable, $fullMarkdown);

        // 添加对比内容
        $fullMarkdown .= $markdownQuestionsComparison;

        // 如果有失败的测试，添加失败摘要
        if (! empty($failedModels)) {
            $fullMarkdown .= "\n## 测试失败摘要\n\n";
            $fullMarkdown .= "| 模型 | 问题类型 | 错误信息 |\n";
            $fullMarkdown .= "|:-----|:---------|:--------|\n";

            foreach ($failedModels as $failure) {
                $fullMarkdown .= "| {$failure['model']} | {$failure['question_type']} | " . $failure['error'] . " |\n";
            }
            $fullMarkdown .= "\n";
        }

        // 添加结论和生成时间
        $fullMarkdown .= "\n## 结论\n\n";
        $fullMarkdown .= '各模型在不同类型问题上表现各异。总体而言，';

        // 找出总体最佳模型
        reset($resultSummary);
        $bestModel = key($resultSummary);
        $worstModel = array_key_last($resultSummary);

        $fullMarkdown .= "**{$bestModel}** 在大多数测试中表现最佳，而 **{$worstModel}** 的综合得分相对较低。\n\n";

        // 找出最擅长知识型问题的模型
        $bestKnowledgeModel = null;
        $bestKnowledgeScore = 0;
        foreach ($resultSummary as $modelName => $data) {
            if (isset($data['questions']['knowledge'])
                && ($data['questions']['knowledge']['status'] ?? '') === 'success'
                && ($data['questions']['knowledge']['score'] ?? 0) > $bestKnowledgeScore) {
                $bestKnowledgeModel = $modelName;
                $bestKnowledgeScore = $data['questions']['knowledge']['score'];
            }
        }
        if ($bestKnowledgeModel) {
            $fullMarkdown .= "最擅长处理知识型问题的模型是：**{$bestKnowledgeModel}**\n\n";
        }

        // 找出最擅长编写代码的模型
        $bestCodingModel = null;
        $bestCodingScore = 0;
        foreach ($resultSummary as $modelName => $data) {
            if (isset($data['questions']['coding'])
                && ($data['questions']['coding']['status'] ?? '') === 'success'
                && ($data['questions']['coding']['score'] ?? 0) > $bestCodingScore) {
                $bestCodingModel = $modelName;
                $bestCodingScore = $data['questions']['coding']['score'];
            }
        }
        if ($bestCodingModel) {
            $fullMarkdown .= "最擅长编写代码的模型是：**{$bestCodingModel}**\n\n";
        }

        // 找出响应速度最快的模型
        $fastestModel = null;
        $fastestTime = PHP_INT_MAX;
        foreach ($resultSummary as $modelName => $data) {
            if ($data['avg_response_time'] > 0 && $data['avg_response_time'] < $fastestTime) {
                $fastestModel = $modelName;
                $fastestTime = $data['avg_response_time'];
            }
        }
        if ($fastestModel) {
            $fullMarkdown .= "响应速度最快的模型是：**{$fastestModel}** (平均 {$fastestTime} 秒)\n\n";
        }

        $fullMarkdown .= "---\n\n";
        $fullMarkdown .= '生成时间: ' . date('Y-m-d H:i:s') . " | 总测试时长: {$totalTestTime} 秒";

        // 重新写入完整的Markdown文件
        file_put_contents($this->markdownFile, $fullMarkdown);
    }
}

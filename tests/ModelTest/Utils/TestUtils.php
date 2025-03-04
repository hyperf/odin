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

namespace HyperfTest\Odin\ModelTest\Utils;

class TestUtils
{
    /**
     * 定义一些好看的分隔符和样式.
     */
    public static function getSeparator(): string
    {
        return str_repeat('=', 80);
    }

    /**
     * 定义一些好看的分隔符和样式.
     */
    public static function getThinSeparator(): string
    {
        return str_repeat('-', 80);
    }

    /**
     * 定义一些好看的分隔符和样式.
     */
    public static function getStarLine(): string
    {
        return '★' . str_repeat('☆', 39) . '★';
    }

    /**
     * 简单估算Token数量.
     */
    public static function estimateTokens(string $content): int
    {
        return (int) (mb_strlen($content) / 3);
    }

    /**
     * 根据评分计算性能等级.
     */
    public static function getPerformanceRating(float $normalizedScore): string
    {
        if ($normalizedScore >= 9) {
            return '🌟 优秀';
        }
        if ($normalizedScore >= 7) {
            return '✅ 良好';
        }
        if ($normalizedScore >= 5) {
            return '⚠️ 一般';
        }
        return '❌ 不佳';
    }

    /**
     * 计算模型得分.
     */
    public static function calculateScore(float $responseTime, int $estimatedTokens, array $questionData): float
    {
        // 时间分数：相对于预期时间的比例（每个复杂度级别预计需要5秒）
        $timeScore = $responseTime / ($questionData['complexity'] * 5);
        // Token分数：相对于预期token的比例
        $tokenRatio = $estimatedTokens / $questionData['expected_tokens'];
        $tokenScore = $tokenRatio;
        // 综合得分：时间和token的加权平均
        $combinedScore = ($timeScore * 0.6) + ($tokenScore * 0.4);
        return min(10, max(1, round(10 - ($combinedScore * 5), 1)));
    }

    /**
     * 确保目录存在.
     */
    public static function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * 获取Markdown文件ID.
     */
    public static function getMarkdownId(string $name): string
    {
        return strtolower(str_replace([' ', '.'], '-', $name));
    }
}

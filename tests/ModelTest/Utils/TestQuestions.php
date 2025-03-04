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

class TestQuestions
{
    /**
     * 获取所有测试问题.
     */
    public static function getQuestions(): array
    {
        return [
            'basic' => [
                'type' => '基础问题',
                'question' => '你好，请介绍你自己',
                'complexity' => 1,
                'expected_tokens' => 300,
                'category' => '基础能力',
                'description' => '测试模型的基本自我介绍能力',
            ],
            'knowledge' => [
                'type' => '知识测试',
                'question' => '请解释量子纠缠的原理，并举一个实际应用的例子',
                'complexity' => 3,
                'expected_tokens' => 600,
                'category' => '专业知识',
                'description' => '测试模型对复杂科学概念的理解和解释能力',
            ],
            'reasoning' => [
                'type' => '推理能力',
                'question' => '如果地球上突然所有昆虫都消失了，生态系统会有什么连锁反应？',
                'complexity' => 4,
                'expected_tokens' => 800,
                'category' => '逻辑思维',
                'description' => '测试模型的因果推理和系统思考能力',
            ],
            'creativity' => [
                'type' => '创造力',
                'question' => '设计一个解决城市交通拥堵的创新方案',
                'complexity' => 3,
                'expected_tokens' => 700,
                'category' => '创造性思维',
                'description' => '测试模型的创新和问题解决能力',
            ],
            'coding' => [
                'type' => '代码能力',
                'question' => '请编写一个PHP函数，实现斐波那契数列的计算',
                'complexity' => 2,
                'expected_tokens' => 400,
                'category' => '编程技能',
                'description' => '测试模型的代码生成能力',
            ],
            'ethics' => [
                'type' => '伦理讨论',
                'question' => '讨论人工智能发展中的隐私保护与技术进步如何平衡',
                'complexity' => 4,
                'expected_tokens' => 900,
                'category' => '伦理思考',
                'description' => '测试模型对伦理问题的理解和平衡讨论能力',
            ],
        ];
    }

    /**
     * 获取单个测试问题.
     */
    public static function getQuestion(string $key): ?array
    {
        $questions = self::getQuestions();
        return $questions[$key] ?? null;
    }

    /**
     * 获取所有测试问题的键.
     */
    public static function getQuestionKeys(): array
    {
        return array_keys(self::getQuestions());
    }
}

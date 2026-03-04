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

namespace HyperfTest\Odin\Cases\Utils;

use Hyperf\Odin\Utils\ModelUtil;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(ModelUtil::class)]
class ModelUtilTest extends AbstractTestCase
{
    /**
     * 测试 isQwenModel 方法.
     */
    #[DataProvider('qwenModelProvider')]
    public function testIsQwenModel(string $model, bool $expected): void
    {
        $this->assertSame($expected, ModelUtil::isQwenModel($model));
    }

    /**
     * Qwen模型数据提供者.
     */
    public static function qwenModelProvider(): array
    {
        return [
            ['qwen-turbo', true],
            ['qwen-plus', true],
            ['qwen-max', true],
            ['Qwen-Turbo', true], // 测试大小写不敏感
            ['QWEN-MAX', true],
            ['gpt-3.5-turbo', false],
            ['deepseek-chat', false],
            ['kimi-k2.5', false],
        ];
    }

    /**
     * 测试 isDeepSeekModel 方法.
     */
    #[DataProvider('deepSeekModelProvider')]
    public function testIsDeepSeekModel(string $model, bool $expected): void
    {
        $this->assertSame($expected, ModelUtil::isDeepSeekModel($model));
    }

    /**
     * DeepSeek模型数据提供者.
     */
    public static function deepSeekModelProvider(): array
    {
        return [
            ['deepseek-chat', true],
            ['deepseek-coder', true],
            ['DeepSeek-Chat', true], // 测试大小写不敏感
            ['DEEPSEEK-CODER', true],
            ['gpt-3.5-turbo', false],
            ['qwen-turbo', false],
            ['kimi-k2.5', false],
        ];
    }

    /**
     * 测试 isKimiModel 方法.
     */
    #[DataProvider('kimiModelProvider')]
    public function testIsKimiModel(string $model, bool $expected): void
    {
        $this->assertSame($expected, ModelUtil::isKimiModel($model));
    }

    /**
     * Kimi模型数据提供者.
     */
    public static function kimiModelProvider(): array
    {
        return [
            ['kimi-k2.5', true],
            ['kimi-k1.5', true],
            ['Kimi-K2.5', true], // 测试大小写不敏感
            ['KIMI-K1.5', true],
            ['gpt-3.5-turbo', false],
            ['qwen-turbo', false],
            ['deepseek-chat', false],
        ];
    }

    /**
     * 测试 getProviderType 方法.
     */
    #[DataProvider('providerTypeProvider')]
    public function testGetProviderType(string $model, string $expected): void
    {
        $this->assertSame($expected, ModelUtil::getProviderType($model));
    }

    /**
     * 提供商类型数据提供者.
     */
    public static function providerTypeProvider(): array
    {
        return [
            // Qwen模型应返回dashscope
            ['qwen-turbo', 'dashscope'],
            ['qwen-plus', 'dashscope'],
            ['Qwen-Max', 'dashscope'],

            // DeepSeek模型应返回deepseek
            ['deepseek-chat', 'deepseek'],
            ['deepseek-coder', 'deepseek'],
            ['DeepSeek-Chat', 'deepseek'],

            // Kimi模型应返回deepseek（复用DeepSeek客户端）
            ['kimi-k2.5', 'deepseek'],
            ['kimi-k1.5', 'deepseek'],
            ['Kimi-K2.5', 'deepseek'],

            // 其他模型应返回openai
            ['gpt-3.5-turbo', 'openai'],
            ['gpt-4', 'openai'],
            ['claude-3', 'openai'],
        ];
    }
}

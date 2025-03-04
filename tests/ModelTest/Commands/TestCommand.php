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

namespace HyperfTest\Odin\ModelTest\Commands;

use HyperfTest\Odin\ModelTest\ModelTestCase;

/**
 * 模型测试命令.
 */
class TestCommand
{
    /**
     * 命令行参数.
     */
    private array $options;

    /**
     * 测试实例.
     */
    private ModelTestCase $testCase;

    public function __construct(array $options)
    {
        $this->options = $options;
        $this->testCase = new ModelTestCase();
    }

    /**
     * 运行命令.
     */
    public function run(): void
    {
        // 显示帮助
        if (isset($this->options['h']) || isset($this->options['help'])) {
            $this->testCase->showHelp();
            return;
        }

        // 测试模式
        $isStream = isset($this->options['s']) || isset($this->options['stream']);

        // 测试单个模型的单个问题
        if ((isset($this->options['m']) || isset($this->options['model']))
            && (isset($this->options['q']) || isset($this->options['question']))) {
            $modelName = $this->options['m'] ?? $this->options['model'] ?? '';
            $questionKey = $this->options['q'] ?? $this->options['question'] ?? '';

            $this->testCase->runSingleTest($modelName, $questionKey, $isStream);
            return;
        }

        // 测试所有模型
        $this->testCase->runAllTests($isStream);
    }

    /**
     * 解析命令行参数.
     */
    public static function parseArgs(): array
    {
        return getopt('am:q:sh', ['all', 'model:', 'question:', 'stream', 'help']);
    }
}

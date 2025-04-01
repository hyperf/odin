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

namespace HyperfTest\Odin\ModelTest;

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\ModelMapper;
use HyperfTest\Odin\ModelTest\Formatters\MarkdownFormatter;
use HyperfTest\Odin\ModelTest\TestRunners\NonStreamTestRunner;
use HyperfTest\Odin\ModelTest\TestRunners\StreamTestRunner;
use HyperfTest\Odin\ModelTest\Utils\TestQuestions;
use HyperfTest\Odin\ModelTest\Utils\TestUtils;
use Throwable;

class ModelTestCase
{
    /**
     * @var Container 容器
     */
    protected Container $container;

    /**
     * @var ModelMapper 模型映射器
     */
    protected ModelMapper $modelMapper;

    /**
     * 初始化测试环境.
     */
    public function __construct()
    {
        // 定义 BASE_PATH 常量
        if (! defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2));
        }

        // 初始化容器
        ClassLoader::init();
        $this->container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
        $this->modelMapper = $this->container->get(ModelMapper::class);
    }

    /**
     * 运行所有模型的测试.
     */
    public function runAllTests(bool $isStream = false): void
    {
        // 创建Markdown文件
        $timestamp = date('Y-m-d_H-i-s');
        $reportDir = BASE_PATH . '/runtime';
        $markdownFile = $reportDir . "/model_test_results_{$timestamp}"
            . ($isStream ? '_stream' : '') . '.md';

        // 确保目录存在
        TestUtils::ensureDirectoryExists($reportDir);

        // 获取测试问题
        $testQuestions = TestQuestions::getQuestions();

        // 创建格式化工具
        $formatter = new MarkdownFormatter($markdownFile, $isStream ? 'stream' : 'non-stream');
        $formatter->initMarkdownReport($testQuestions);

        // 添加脚本说明
        echo PHP_EOL . '聊天模型' . ($isStream ? '流式' : '非流式') . '测试脚本' . PHP_EOL;
        echo '此脚本将测试所有聊天模型的' . ($isStream ? '流式' : '非流式') . '回答能力' . PHP_EOL;
        echo "测试结果将保存到: {$markdownFile}" . PHP_EOL . PHP_EOL;

        // 创建并运行测试运行器
        if ($isStream) {
            $runner = new StreamTestRunner($this->modelMapper, $formatter);
        } else {
            $runner = new NonStreamTestRunner($this->modelMapper, $formatter);
        }

        $runner->testAllModels($testQuestions);

        echo "📊 完整测试报告已保存到: {$markdownFile}" . PHP_EOL;
    }

    /**
     * 运行单个模型的单个问题测试.
     */
    public function runSingleTest(string $modelName, string $questionKey, bool $isStream = false): void
    {
        // 验证模型是否存在
        if (! isset($this->modelMapper->getModels('chat')[$modelName])) {
            echo "错误: 模型 '{$modelName}' 不存在" . PHP_EOL;
            echo '可用的模型:' . PHP_EOL;
            foreach ($this->modelMapper->getModels('chat') as $name => $object) {
                echo "  - {$name}" . PHP_EOL;
            }
            exit(1);
        }

        // 获取问题数据
        $questionData = TestQuestions::getQuestion($questionKey);
        if ($questionData === null) {
            echo "错误: 问题类型 '{$questionKey}' 不存在" . PHP_EOL;
            echo '可用的问题类型:' . PHP_EOL;
            foreach (TestQuestions::getQuestions() as $key => $data) {
                echo "  - {$key}: {$data['type']} - {$data['question']}" . PHP_EOL;
            }
            exit(1);
        }

        // 开始测试
        try {
            echo PHP_EOL . TestUtils::getSeparator() . PHP_EOL;
            echo ($isStream ? '流式' : '') . '测试模型: ' . $modelName . PHP_EOL;
            echo TestUtils::getStarLine() . PHP_EOL;

            echo PHP_EOL . '📝 测试类型: ' . $questionData['type'] . PHP_EOL;
            echo '📋 问题描述: ' . $questionData['description'] . PHP_EOL;
            echo '⭐ 复杂度: ' . str_repeat('★', $questionData['complexity']) . PHP_EOL;
            echo '🏷️ 类别: ' . $questionData['category'] . PHP_EOL;

            echo PHP_EOL . '💬 用户: ' . $questionData['question'] . PHP_EOL;
            echo TestUtils::getThinSeparator() . PHP_EOL;
            echo '🤖 助手: ';

            // 执行测试
            if ($isStream) {
                $runner = new StreamTestRunner($this->modelMapper, new MarkdownFormatter('', 'stream'));
                $result = $runner->testSingleQuestion($modelName, $questionData);

                echo PHP_EOL . PHP_EOL;

                // 打印详细结果
                echo TestUtils::getThinSeparator() . PHP_EOL;
                echo '📊 测试结果:' . PHP_EOL;
                echo "⏱️ 响应时间: {$result['response_time']} 秒" . PHP_EOL;
                echo "📝 输出token数: {$result['estimated_tokens']} (预期的 "
                    . round($result['token_ratio'] * 100) . '%)' . PHP_EOL;

                if ($result['reasoning_tokens'] > 0) {
                    echo "🧠 思考过程token数: {$result['reasoning_tokens']}" . PHP_EOL;
                    echo "🔄 思考/输出比例: {$result['reasoning_ratio']}%" . PHP_EOL;
                    echo "📊 总token数: {$result['total_tokens']}" . PHP_EOL;
                }

                echo "🏆 性能得分: {$result['score']}/10 - " . TestUtils::getPerformanceRating($result['score']) . PHP_EOL;
            } else {
                $runner = new NonStreamTestRunner($this->modelMapper, new MarkdownFormatter('', 'non-stream'));
                $result = $runner->testSingleQuestion($modelName, $questionData);

                // 打印详细结果
                echo TestUtils::getThinSeparator() . PHP_EOL;
                echo '📊 测试结果:' . PHP_EOL;
                echo "⏱️ 响应时间: {$result['response_time']} 秒" . PHP_EOL;
                echo "📝 估计token数: {$result['estimated_tokens']} (预期的 "
                    . round($result['token_ratio'] * 100) . '%)' . PHP_EOL;
                echo "🏆 性能得分: {$result['score']}/10 - " . TestUtils::getPerformanceRating($result['score']) . PHP_EOL;
            }

            echo PHP_EOL . '✅ 测试完成!' . PHP_EOL;
        } catch (Throwable $e) {
            echo PHP_EOL . '❌ 测试失败: ' . $e->getMessage() . PHP_EOL;
            echo '异常类型: ' . get_class($e) . PHP_EOL;
            echo '文件: ' . $e->getFile() . PHP_EOL;
            echo '行数: ' . $e->getLine() . PHP_EOL;
            echo '堆栈跟踪:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
            exit(1);
        }
    }

    /**
     * 显示帮助信息.
     */
    public function showHelp(): void
    {
        echo PHP_EOL . '模型测试工具' . PHP_EOL;
        echo '用法: php tests/bin/model-test.php [选项]' . PHP_EOL;
        echo '选项:' . PHP_EOL;
        echo '  -a, --all         测试所有模型 (默认)' . PHP_EOL;
        echo '  -m, --model       指定要测试的模型名称' . PHP_EOL;
        echo '  -q, --question    指定要测试的问题类型' . PHP_EOL;
        echo '  -s, --stream      使用流式测试模式' . PHP_EOL;
        echo '  -h, --help        显示此帮助信息' . PHP_EOL . PHP_EOL;

        echo '可用的模型:' . PHP_EOL;
        foreach ($this->modelMapper->getModels('chat') as $modelName => $modelObject) {
            echo "  - {$modelName}" . PHP_EOL;
        }

        echo PHP_EOL . '可用的问题类型:' . PHP_EOL;
        foreach (TestQuestions::getQuestions() as $key => $questionData) {
            echo "  - {$key}: {$questionData['type']} - {$questionData['question']}" . PHP_EOL;
        }

        echo PHP_EOL . '示例:' . PHP_EOL;
        echo '  php tests/bin/model-test.php -a                  # 测试所有模型（非流式）' . PHP_EOL;
        echo '  php tests/bin/model-test.php -a -s               # 测试所有模型（流式）' . PHP_EOL;
        echo '  php tests/bin/model-test.php -m "模型名称" -q basic  # 测试指定模型的基础问题（非流式）' . PHP_EOL;
        echo '  php tests/bin/model-test.php -m "模型名称" -q basic -s # 测试指定模型的基础问题（流式）' . PHP_EOL;
    }
}

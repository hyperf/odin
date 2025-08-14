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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 2));

require_once dirname(__FILE__, 3) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\Model\LLMEmbeddingInputTooLargeException;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\TextSplitter\RecursiveCharacterTextSplitter;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

echo "=== 嵌入错误处理指南 ===\n\n";

/**
 * 示例：正确处理嵌入输入过大错误.
 */
function handleEmbeddingWithErrorHandling(string $text): array
{
    try {
        // 这里模拟一个嵌入模型（实际使用时替换为真实的模型配置）
        $model = ModelFactory::create(
            implementation: OpenAIModel::class,
            modelName: 'text-embedding-ada-002',
            config: [
                'api_key' => env('OPENAI_API_KEY', 'sk-test'),
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            ],
            modelOptions: ModelOptions::fromArray([
                'embedding' => true,
                'vector_size' => 1536,
            ])
        );

        // 直接尝试嵌入
        return $model->embedding($text)->getEmbeddings();
    } catch (LLMEmbeddingInputTooLargeException $e) {
        echo "🔴 检测到嵌入输入过大错误:\n";
        echo "   错误消息: {$e->getMessage()}\n";
        echo '   模型: ' . ($e->getModel() ?? 'N/A') . "\n";
        echo '   输入长度: ' . ($e->getInputLength() ?? 'N/A') . " 字符\n";
        echo '   最大长度: ' . ($e->getMaxInputLength() ?? 'N/A') . "\n";
        echo "   建议: {$e->getSuggestion()}\n\n";

        // 🔧 自动修复：使用文本分割器处理大文本
        echo "🔧 正在自动使用文本分割器处理...\n";

        $splitter = new RecursiveCharacterTextSplitter(
            chunkSize: 1000,        // 每块1000字符
            chunkOverlap: 100,      // 重叠100字符
            separators: ["\n\n", "\n", '。', '.', ' '],
            keepSeparator: false,
            addStartIndex: true
        );

        // 分割文本
        $documents = $splitter->createDocuments([$text]);
        echo '   文本已分割为 ' . count($documents) . " 个块\n";

        // 分别处理每个块
        $embeddings = [];
        foreach ($documents as $i => $doc) {
            try {
                echo '   处理第 ' . ($i + 1) . " 块...\n";
                $embedding = $model->embedding($doc->getContent());
                $embeddings[] = [
                    'chunk_index' => $i,
                    'start_index' => $doc->getMetadata()['start_index'] ?? null,
                    'content' => substr($doc->getContent(), 0, 50) . '...',
                    'embedding' => $embedding->toArray(),
                ];

                // 避免频繁请求
                usleep(100000); // 100ms延迟
            } catch (LLMException $chunkException) {
                echo '   ⚠️  第 ' . ($i + 1) . " 块处理失败: {$chunkException->getMessage()}\n";
            }
        }

        echo '✅ 成功处理 ' . count($embeddings) . " 个文本块\n\n";
        return $embeddings;
    } catch (LLMException $e) {
        echo "🔴 其他LLM错误:\n";
        echo '   类型: ' . get_class($e) . "\n";
        echo "   错误消息: {$e->getMessage()}\n";
        echo "   错误代码: {$e->getErrorCode()}\n\n";

        // 根据不同的错误类型提供建议
        $suggestions = [
            'LLMRateLimitException' => '请减少请求频率或等待一段时间后重试',
            'LLMInvalidApiKeyException' => '请检查API密钥是否正确',
            'LLMNetworkException' => '请检查网络连接或稍后重试',
            'LLMContentFilterException' => '请修改输入内容，避免敏感信息',
        ];

        $className = basename(str_replace('\\', '/', get_class($e)));
        if (isset($suggestions[$className])) {
            echo "   建议: {$suggestions[$className]}\n";
        }

        return [];
    } catch (Exception $e) {
        echo "🔴 未知错误: {$e->getMessage()}\n";
        return [];
    }
}

// 测试用例

echo "1. 测试正常长度文本:\n";
echo str_repeat('-', 50) . "\n";
$normalText = '这是一段正常长度的测试文本，用于验证嵌入功能是否正常工作。';
$result1 = handleEmbeddingWithErrorHandling($normalText);
echo '结果: ' . (empty($result1) ? '失败' : '成功') . "\n\n";

echo "2. 测试超长文本（会触发输入过大错误）:\n";
echo str_repeat('-', 50) . "\n";
$longText = str_repeat('这是一段用于测试的超长文本内容，包含大量重复的内容来模拟真实场景中可能遇到的长文档。', 500);
$result2 = handleEmbeddingWithErrorHandling($longText);
echo '结果: ' . (empty($result2) ? '失败' : '处理了 ' . count($result2) . ' 个文本块') . "\n\n";

echo "=== 最佳实践总结 ===\n";
echo "1. 🔍 预检查：在发送嵌入请求前检查文本长度\n";
echo "2. 🔧 文本分割：使用 RecursiveCharacterTextSplitter 处理长文本\n";
echo "3. ⏱️  批量处理：分批处理多个文本块，添加适当延迟\n";
echo "4. 🧹 内容清理：移除不必要的多媒体内容和格式标记\n";
echo "5. 🔁 错误重试：实现智能重试机制\n";
echo "6. 📊 结果合并：根据需要合并多个文本块的嵌入结果\n\n";

echo "现在，当你遇到 'input is too large' 错误时，系统会:\n";
echo "✅ 显示明确的错误信息：'嵌入请求输入内容过大'\n";
echo "✅ 提供模型信息和输入长度统计\n";
echo "✅ 给出具体的解决建议\n";
echo "✅ 可以使用异常的方法获取详细信息进行自动处理\n";

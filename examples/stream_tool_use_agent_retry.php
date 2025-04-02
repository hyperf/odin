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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\Driver\InMemoryDriver;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Memory\Policy\LimitCountPolicy;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Model\OpenAIModel;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型
$model = ModelFactory::create(
    implementation: OpenAIModel::class,
    modelName: 'deepseek-chat',
    config: [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_API_BASE'),
    ],
    modelOptions: ModelOptions::fromArray([
        'chat' => true,
        'function_call' => true,
    ]),
    apiOptions: ApiOptions::fromArray([
        'timeout' => [
            'connection' => 5.0,  // 连接超时（秒）
            'read' => 300.0,      // 读取超时（秒）
        ],
    ]),
    logger: $logger
);

// 初始化内存管理器(简化版)
$memory = new MemoryManager(new InMemoryDriver(), new LimitCountPolicy(['max_count' => 5]));
$memory->addSystemMessage(new SystemMessage('你是一个能够使用工具的AI助手，当需要使用工具时，请明确指出工具的作用和使用步骤。'));

// 定义天气查询工具
$weatherTool = new ToolDefinition(
    name: 'GetWeather',
    description: '获取指定城市的天气信息',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'location' => [
                'type' => 'string',
                'description' => '城市名称，如：北京、上海、广州等',
            ],
        ],
        'required' => ['location'],
    ]),
    toolHandler: function ($params) {
        // 这里故意返回空字符串，因为我们只是测试工具调用失败的情况
        return '温度为 48 度';
    }
);

/**
 * 专门为流式测试工具调用失败的Agent
 */
class StreamToolCallFailureAgent extends ToolUseAgent
{
    /**
     * 模拟流式响应中工具调用失败的情况.
     */
    public function chatStreamed(?UserMessage $input = null): Generator
    {
        // 输出初始提示
        echo "====> 开始模拟流式响应，模拟工具调用失败的情况 <====\n";
        
        // 创建一个信息
        $assistantMessage = new AssistantMessage('我需要查询天气信息来回答您的问题。');
        
        // 输出不包含工具调用的消息，但将finishReason设为tool_calls
        $choice = new ChatCompletionChoice(
            $assistantMessage,
            0,
            null,
            'tool_calls' // 关键点：finish_reason为tool_calls，但没有工具调用
        );
        
        // 先输出普通消息
        echo "输出无工具调用但finish_reason为tool_calls的消息...\n";
        yield $choice;
        
        echo "检查是否会触发工具调用失败检测和重试逻辑...\n";
        
        // 验证是否触发了我们的toolCall重试逻辑
        $result = yield from parent::chatStreamed($input);
        
        echo "====> 流式响应结束 <====\n";
        
        return $result;
    }
}

// 创建代理实例
$agent = new StreamToolCallFailureAgent(
    model: $model,
    memory: $memory,
    tools: [$weatherTool],
    temperature: 0.3,
    logger: $logger
);

// 测试
echo "===== 流式工具调用失败重试测试 =====\n";
$start = microtime(true);

// 简单的用户查询
$userMessage = new UserMessage('请告诉我北京的天气怎么样？');

// 执行流式聊天
$generator = $agent->chatStreamed($userMessage);

$fullContent = '';
// 处理流式响应
foreach ($generator as $choice) {
    if ($choice instanceof ChatCompletionChoice) {
        $message = $choice->getMessage();
        $content = $message->getContent();
        if (!empty($content)) {
            echo "接收到内容: " . $content . "\n";
            $fullContent .= $content;
        }
    }
}

echo "\n";
echo '最终内容：' . $fullContent . "\n";
echo '耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL; 
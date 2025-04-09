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
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
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

class FirstToolFailAgent extends ToolUseAgent
{
    // 记录是否已经检测到第一次的工具调用
    private bool $firstToolCallDetected = false;

    /**
     * 重写chat方法，模拟第一次工具调用失败的情况.
     */
    public function chat(?UserMessage $input = null): ChatCompletionResponse
    {
        $gen = $this->call($input);

        while ($gen->valid()) {
            $response = $gen->current();

            // 检测到第一次返回且还没有注入过错误
            if ($response instanceof ChatCompletionResponse && ! $this->firstToolCallDetected) {
                $choice = $response->getFirstChoice();
                if ($choice && $choice->getMessage() instanceof AssistantMessage) {
                    // 标记已处理过第一次工具调用
                    $this->firstToolCallDetected = true;

                    // 创建新的不包含工具调用的响应，但设置finish_reason为tool_calls
                    // 这会触发我们的重试逻辑
                    $assistantMessage = new AssistantMessage(
                        '我需要查询天气信息来回答您的问题。'
                    ); // 不包含工具调用

                    // 创建新的Choice，将finishReason设为tool_calls
                    $newChoice = new ChatCompletionChoice(
                        $assistantMessage,
                        $choice->getIndex(),
                        $choice->getLogprobs(),
                        'tool_calls' // 关键：finish_reason设为tool_calls
                    );

                    // 更新响应中的选择列表
                    $response->setChoices([$newChoice]);
                }
            }

            $gen->next();
        }

        return $gen->getReturn();
    }
}

// 创建代理实例
$agent = new FirstToolFailAgent(
    model: $model,
    memory: $memory,
    tools: [$weatherTool],
    temperature: 0.3,
    logger: $logger
);

// 测试
echo "===== 工具调用失败重试测试 =====\n";
$start = microtime(true);

// 简单的用户查询
$userMessage = new UserMessage('请告诉我北京的天气怎么样？');

// 执行聊天
$response = $agent->chat($userMessage);

// 输出结果
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getContent();
}

echo "\n";
echo '耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;

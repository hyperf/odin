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
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建 AWS Bedrock 模型实例
// 使用 Claude 3 Sonnet 模型 ID
$model = new AwsBedrockModel(
    'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    new Logger(),
);
$model->setModelOptions(new ModelOptions([
    'function_call' => true,
]));
$model->setApiRequestOptions(new ApiOptions([
    // 如果你的环境不需要代码，那就不用
    'proxy' => env('HTTP_CLIENT_PROXY'),
]));

echo '=== AWS Bedrock Claude 工具调用测试 ===' . PHP_EOL;
echo '支持函数调用功能' . PHP_EOL . PHP_EOL;

// 定义一个天气查询工具
$weatherTool = new ToolDefinition(
    name: 'weather',
    description: '查询指定城市的天气信息',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => '要查询天气的城市名称',
            ],
        ],
        'required' => ['city'],
    ]),
    toolHandler: function ($params) {
        $city = $params['city'];
        // 模拟天气数据
        $weatherData = [
            '北京' => ['temperature' => '25°C', 'condition' => '晴朗', 'humidity' => '45%'],
            '上海' => ['temperature' => '28°C', 'condition' => '多云', 'humidity' => '60%'],
            '广州' => ['temperature' => '30°C', 'condition' => '阵雨', 'humidity' => '75%'],
            '深圳' => ['temperature' => '29°C', 'condition' => '晴朗', 'humidity' => '65%'],
        ];

        if (isset($weatherData[$city])) {
            return $weatherData[$city];
        }
        return ['error' => '没有找到该城市的天气信息'];
    }
);

$toolMessages = [
    new SystemMessage('你是一位有用的天气助手，可以查询天气信息。'),
    new UserMessage('明天上海的天气如何？'),
];

$start = microtime(true);

// 使用工具进行API调用
$response = $model->chat($toolMessages, 0.7, 0, [], [$weatherTool]);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo '响应内容: ' . ($message->getContent() ?? '无内容，可能是工具调用') . PHP_EOL;

    // 检查是否有工具调用
    $toolCalls = $message->getToolCalls();
    if (! empty($toolCalls)) {
        echo '工具调用信息:' . PHP_EOL;
        foreach ($toolCalls as $toolCall) {
            echo '- 工具名称: ' . $toolCall->getName() . PHP_EOL;
            echo '- 参数: ' . json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        // 模拟工具执行结果
        echo PHP_EOL . '模拟工具执行...' . PHP_EOL;

        // 创建工具响应消息
        $toolContent = json_encode([
            'temperature' => '22°C',
            'condition' => '晴天',
            'humidity' => '65%',
            'wind' => '东北风 3级',
        ]);

        $toolResponseMessage = new ToolMessage($toolContent, $toolCall->getId(), $weatherTool->getName(), $toolCall->getArguments());

        // 添加到对话中
        $toolMessages[] = $message; // 添加助手的工具调用消息
        $toolMessages[] = $toolResponseMessage; // 添加工具响应

        // 继续对话
        $continueResponse = $model->chat($toolMessages, 0.7, 0, [], [$weatherTool]);
        $continueMessage = $continueResponse->getFirstChoice()->getMessage();
        if ($continueMessage instanceof AssistantMessage) {
            echo PHP_EOL . '助手最终回复:' . PHP_EOL;
            echo $continueMessage->getContent() . PHP_EOL;
        }
    }
}

echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

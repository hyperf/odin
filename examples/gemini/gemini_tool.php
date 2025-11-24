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
use Hyperf\Odin\Model\GeminiModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// Create Gemini model instance
// Using Gemini 2.5 Flash model
$model = new GeminiModel(
    'gemini-2.5-flash',
    [
        'api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'base_url' => env('GOOGLE_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
    new Logger(),
);
$model->setModelOptions(new ModelOptions([
    'function_call' => true,
]));
$model->setApiRequestOptions(new ApiOptions([
    // Add proxy if needed
    'proxy' => env('HTTP_CLIENT_PROXY'),
]));

echo '=== Gemini 工具调用测试 ===' . PHP_EOL;
echo '支持函数调用功能' . PHP_EOL . PHP_EOL;

// Define a weather query tool
$weatherTool = new ToolDefinition(
    name: 'weather',
    description: '查询指定城市的天气信息。当用户询问天气时，必须使用此工具来获取天气数据。',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'city' => [
                'type' => 'string',
                'description' => '要查询天气的城市名称，例如：北京、上海、广州、深圳',
            ],
        ],
        'required' => ['city'],
    ]),
    toolHandler: function ($params) {
        $city = $params['city'];
        // Simulate weather data
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
    new SystemMessage('你是一位有用的天气助手。当用户询问任何城市的天气信息时，你必须使用 weather 工具来查询天气数据，然后根据查询结果回答用户。'),
    new UserMessage('请查询上海的天气。'),
];

$start = microtime(true);

// Use tool for API call
$response = $model->chat($toolMessages, 0.7, 0, [], [$weatherTool]);

// Output complete response
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo '响应内容: ' . ($message->getContent() ?? '无内容，可能是工具调用') . PHP_EOL;

    // Check if there are tool calls
    $toolCalls = $message->getToolCalls();
    if (! empty($toolCalls)) {
        echo '工具调用信息:' . PHP_EOL;
        foreach ($toolCalls as $toolCall) {
            echo '- 工具名称: ' . $toolCall->getName() . PHP_EOL;
            echo '- 参数: ' . json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        // Simulate tool execution result
        echo PHP_EOL . '模拟工具执行...' . PHP_EOL;

        // Add assistant's tool call message to conversation
        $toolMessages[] = $message;

        // Create tool response message for each tool call
        foreach ($toolCalls as $toolCall) {
            // Create tool response message
            $toolContent = json_encode([
                'temperature' => '22°C',
                'condition' => '晴天',
                'humidity' => '65%',
                'wind' => '东北风 3级',
            ]);

            $toolResponseMessage = new ToolMessage($toolContent, $toolCall->getId(), $weatherTool->getName(), $toolCall->getArguments());
            $toolMessages[] = $toolResponseMessage; // Add tool response
        }

        // Continue conversation with all tool responses
        $continueResponse = $model->chat($toolMessages, 0.7, 0, [], [$weatherTool]);
        $continueMessage = $continueResponse->getFirstChoice()->getMessage();
        if ($continueMessage instanceof AssistantMessage) {
            echo PHP_EOL . '助手最终回复:' . PHP_EOL;
            echo $continueMessage->getContent() . PHP_EOL;
        }
    } else {
        echo PHP_EOL . '未检测到工具调用' . PHP_EOL;
    }
}

echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

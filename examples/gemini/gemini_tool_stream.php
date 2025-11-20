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
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
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

echo '=== Gemini 流式工具调用测试 ===' . PHP_EOL;
echo '支持流式函数调用功能' . PHP_EOL . PHP_EOL;

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

// Use streaming API for tool call
echo '流式响应:' . PHP_EOL;
$response = $model->chatStream($toolMessages, 0.7, 0, [], [$weatherTool]);

$streamedContent = '';

// Process streaming response
/** @var ChatCompletionChoice $choice */
foreach ($response->getStreamIterator() as $choice) {
    $message = $choice->getMessage();
    if ($message instanceof AssistantMessage) {
        // Collect streamed content
        $content = $message->getContent();
        if ($content !== null && $content !== '') {
            echo $content;
            $streamedContent .= $content;
        }
    }
}

echo PHP_EOL . PHP_EOL;

// Get complete message after streaming is done
// After streaming completes, we can get the complete message from choices
$completeMessage = null;
$allChoices = $response->getChoices();
if (! empty($allChoices)) {
    // Get the last choice which should have the complete message
    $lastChoice = end($allChoices);
    $completeMessage = $lastChoice->getMessage();
}

// Check if there are tool calls
if ($completeMessage instanceof AssistantMessage) {
    $toolCalls = $completeMessage->getToolCalls();
    if (! empty($toolCalls)) {
        echo '工具调用信息:' . PHP_EOL;
        foreach ($toolCalls as $toolCall) {
            echo '- 工具名称: ' . $toolCall->getName() . PHP_EOL;
            echo '- 参数: ' . json_encode($toolCall->getArguments(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        // Simulate tool execution result
        echo PHP_EOL . '模拟工具执行...' . PHP_EOL;

        // Add assistant's tool call message to conversation
        $toolMessages[] = $completeMessage;

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

        // Continue conversation with all tool responses (also streaming)
        echo PHP_EOL . '助手最终回复（流式）:' . PHP_EOL;
        $continueResponse = $model->chatStream($toolMessages, 0.7, 0, [], [$weatherTool]);

        $finalContent = '';
        /** @var ChatCompletionChoice $choice */
        foreach ($continueResponse->getStreamIterator() as $choice) {
            $message = $choice->getMessage();
            if ($message instanceof AssistantMessage) {
                $content = $message->getContent();
                if ($content !== null && $content !== '') {
                    echo $content;
                    $finalContent .= $content;
                }
            }
        }
        echo PHP_EOL;
    } else {
        echo PHP_EOL . '未检测到工具调用' . PHP_EOL;
        if (! empty($streamedContent)) {
            echo '响应内容: ' . $streamedContent . PHP_EOL;
        }
    }
} else {
    echo PHP_EOL . '响应不是 AssistantMessage 类型' . PHP_EOL;
}

echo PHP_EOL . '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

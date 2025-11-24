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
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsType;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AwsBedrockModel;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

echo '=== AWS Bedrock Custom Client Test (Without AWS SDK) ===' . PHP_EOL . PHP_EOL;

// Create AWS Bedrock model instance with CONVERSE_CUSTOM type
// This uses custom Guzzle + SigV4 implementation instead of AWS SDK
$model = new AwsBedrockModel(
    'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
        'type' => AwsType::CONVERSE_CUSTOM, // Use custom client without AWS SDK
    ],
    new Logger(),
);
$model->setApiRequestOptions(new ApiOptions([
    'proxy' => env('HTTP_CLIENT_PROXY'),
    'http_handler' => env('ODIN_HTTP_HANDLER', 'auto'),
]));

$messages = [
    new SystemMessage('You are a helpful AI assistant. Always include emoji in your responses.'),
    new UserMessage('Explain quantum entanglement in simple terms.'),
];

$start = microtime(true);

// Use non-streaming API
$request = new ChatCompletionRequest($messages);
$request->setThinking([
    'type' => 'enabled',
    'budget_tokens' => 4000,
]);
$response = $model->chatWithRequest($request);

// Output full response
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo 'Response: ' . ($message->getReasoningContent() ?? $message->getContent()) . PHP_EOL;
}

echo PHP_EOL . 'Duration: ' . round(microtime(true) - $start, 2) . ' seconds' . PHP_EOL;

// Output usage information
$usage = $response->getUsage();
echo PHP_EOL . '=== Token Usage ===' . PHP_EOL;
echo 'Input Tokens: ' . $usage->getPromptTokens() . PHP_EOL;
echo 'Output Tokens: ' . $usage->getCompletionTokens() . PHP_EOL;
echo 'Total Tokens: ' . $usage->getTotalTokens() . PHP_EOL;

if ($usage->getCachedTokens() > 0) {
    echo PHP_EOL . 'Cache Hit: ' . $usage->getCachedTokens() . ' tokens' . PHP_EOL;
    echo 'Cache Hit Rate: ' . $usage->getCacheHitRatePercentage() . '%' . PHP_EOL;
}

echo PHP_EOL . '✅ Custom client (without AWS SDK) works perfectly!' . PHP_EOL;

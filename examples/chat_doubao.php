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
use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\DoubaoModel;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$model = new DoubaoModel(
    env('DOUBAO_1_5_THINK_PRO_ENDPOINT'),
    [
        'base_url' => env('DOUBAO_BASE_URL'),
        'api_key' => env('DOUBAO_API_KEY'),
    ],
    new Logger(),
);

$messages = [
    new SystemMessage(''),
    new UserMessage('你是谁？'),
];

$start = microtime(true);

// 使用非流式API调用
$request = new ChatCompletionRequest($messages);
$request->setThinking([
    'type' => 'disabled',
]);
$response = $model->chatWithRequest($request);

// 输出完整响应
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo '<think>' . $message->getReasoningContent() . '</think>' . PHP_EOL;
    echo $message->getContent();
}

echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

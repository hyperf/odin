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
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\DoubaoModel;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$model = new DoubaoModel(
    env('DEEPSPEEK_R1_ENDPOINT'),
    [
        'api_key' => env('DOUBAO_API_KEY'),
        'base_url' => env('DOUBAO_BASE_URL'),
    ],
    new Logger(),
);

$model->setApiRequestOptions(new ApiOptions([
    // HTTP 处理器配置 - 支持环境变量 ODIN_HTTP_HANDLER
    'http_handler' => env('ODIN_HTTP_HANDLER', 'auto'),
]));

$messages = [
    new SystemMessage(''),
    new UserMessage('请解释量子纠缠的原理，并举一个实际应用的例子'),
];
$response = $model->chatStream($messages);

$start = microtime(true);
/** @var ChatCompletionChoice $choice */
foreach ($response->getStreamIterator() as $choice) {
    $message = $choice->getMessage();
    if ($message instanceof AssistantMessage) {
        echo $message->getReasoningContent() ?? $message->getContent();
    }
}
echo PHP_EOL;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

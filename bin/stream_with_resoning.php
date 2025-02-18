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
        'api_key' => env('SKYLARK_API_KEY'),
        'base_url' => env('SKYLARK_HOST'),
        'model' => env('DEEPSPEEK_R1_ENDPOINT'), ]
);

$messages = [
    new SystemMessage(''),
    new UserMessage('你知道海龟汤是什么吗'),
];
$response = $model->chat($messages, stream: true);

echo '开始' . microtime(true) . PHP_EOL;

$reasoningEnd = false;
echo "[深度思考开始]\n";
foreach ($response->getStreamIterator() as $choice) {
    /** @var AssistantMessage $message */
    $message = $choice->getMessage();

    if (! $reasoningEnd && ! $message->hasReasoningContent()) {
        echo "[深度思考结束]\n";
        $reasoningEnd = true;
    }

    echo $message->getReasoningContent() ?: $message->getContent();
}
echo PHP_EOL;
echo '结束' . microtime(true) . PHP_EOL;

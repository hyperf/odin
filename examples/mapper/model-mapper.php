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
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\ModelMapper;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));
$modelMapper = $container->get(ModelMapper::class);

$modelId = \Hyperf\Support\env('MODEL_MAPPER_TEST_MODEL_ID', '');

$model = $modelMapper->getModel($modelId);

$messages = [
    new SystemMessage(''),
    new UserMessage('你好，你是谁'),
];

// 使用非流式API调用
$start = microtime(true);
$response = $model->chat($messages);
$message = $response->getFirstChoice()->getMessage();
if ($message instanceof AssistantMessage) {
    echo $message->getReasoningContent() ?? $message->getContent();
}
echo PHP_EOL;
echo '非流式耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;

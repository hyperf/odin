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

use Hyperf\Di\ClassLoader;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\DoubaoModel;

use function Hyperf\Support\env;

ClassLoader::init();

// $model = new AzureOpenAIModel('gpt-4o-global', [
//    'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
//    'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
//    'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
//    'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
// ]);

$model = new DoubaoModel(
    env('SKYLARK_PRO_32K_ENDPOINT'),
    [
        'api_key' => env('SKYLARK_API_KEY'),
        'base_url' => env('SKYLARK_HOST'),
        'model' => env('SKYLARK_PRO_32K_ENDPOINT'), ]
);

$messages = [
    new SystemMessage(''),
    new UserMessage('帮我写一个 500 字的笑话'),
];
$response = $model->chat($messages, stream: true);

echo '开始' . microtime(true) . PHP_EOL;
foreach ($response->getStreamIterator() as $choice) {
    echo $choice->getMessage()->getContent();
}
echo PHP_EOL;
echo '结束' . microtime(true) . PHP_EOL;

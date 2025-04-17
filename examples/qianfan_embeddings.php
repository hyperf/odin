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
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Model\QianFanModel;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型
$model = ModelFactory::create(
    implementation: QianFanModel::class,
    modelName: env('QIANFAN_EMBEDDINGS_ENDPOINT'),
    config: [
        'api_key' => env('QIANFAN_API_KEY'),
        'base_url' => env('QIANFAN_BASE_URL'),
    ],
    modelOptions: ModelOptions::fromArray([
        'chat' => false,
        'function_call' => false,
        'embedding' => true,
        'multi_modal' => true,
        'vector_size' => 1024,
    ]),
    logger: $logger
);

$data = $model->embeddings('量子纠缠的原理');
var_dump($data->getData());

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

use GuzzleHttp\Exception\ClientException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\VectorStore\Qdrant\Config;
use Hyperf\Qdrant\Api\Collections;
use Hyperf\Qdrant\Api\Points;
use Hyperf\Qdrant\Connection\HttpClient;
use Hyperf\Qdrant\Struct\Collections\Enums\Distance;
use Hyperf\Qdrant\Struct\Collections\VectorParams;
use Hyperf\Qdrant\Struct\Points\ExtendedPointId;
use Hyperf\Qdrant\Struct\Points\Point\PointStruct;
use Hyperf\Qdrant\Struct\Points\Point\ScoredPoint;
use Hyperf\Qdrant\Struct\Points\VectorStruct;
use Hyperf\Qdrant\Struct\Points\WithPayload;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$llm = $container->get(\Hyperf\Odin\LLM::class);

$client = $llm->getAzureOpenAIClient('text-embedding-ada-002');
$conversionId = uniqid();

# 原始语料
$data = [
    '询问:::如何检查变量的类型？',
    '询问:::如果一个数大于10，打印"大于10"，否则打印"小于等于10"。',
    '询问:::如何在列表末尾添加一个元素？',
    '创建代码:::创建一个计算两个数之和的函数',
    '创建代码:::循环打印数字1到10',
    '创建代码:::捕获和处理除以零的异常',
    '询问:::如何从字典中获取特定键的值？',
    '询问:::如何读取一个文本文件的内容？',
    '询问:::如何将一个字符串反转？',
];
// 减少数据量
$data = array_slice($data, 0, 3);
$vectors = array_map(function (string $datum, int $key) use ($client) {
    return new PointStruct(new ExtendedPointId($key + 10000), new VectorStruct($client->embedding($datum)
        ->getData()[0]->embedding), [
        'behavior' => explode(':::', $datum)[0],
        'content' => explode(':::', $datum)[1],
    ]);
}, $data, array_keys($data));

// Collections
$collections = new Collections(new HttpClient(new Config()));
$targetCollectionName = 'test_collection';
try {
    $collectionInfo = $collections->getCollectionInfo($targetCollectionName);
} catch (ClientException $exception) {
    if ($exception->getCode() !== 404) {
        throw $exception;
    }
    $collections->createCollection($targetCollectionName, new VectorParams(1536, Distance::COSINE));
}

// Insert Data
$points = new Points(new HttpClient(new Config()));
$points->setWait(true);
$points->upsertPoints($targetCollectionName, $vectors);

// Search
$vector = new VectorStruct($client->embedding('创建一个整数加减乘除的方法')->getData()[0]->embedding);
$result = $points->searchPoints(collectionName: $targetCollectionName, vector: $vector, limit: 1, withPayload: new WithPayload(true));
$matchResult = array_map(function (ScoredPoint $scoredPoint, int $key) {
    if ($scoredPoint->score > 0.8) {
        return [
            'id' => $scoredPoint->id->id,
            'version' => $scoredPoint->version,
            'score' => $scoredPoint->score,
            'payload' => $scoredPoint->payload,
        ];
    }
}, $result, array_keys($result));
echo json_encode(array_values($matchResult), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

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

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\RWKV\RWKVConfig;
use Hyperf\Odin\VectorStore\Qdrant\Config;
use Hyperf\Qdrant\Api\Collections;
use Hyperf\Qdrant\Api\Points;
use Hyperf\Qdrant\Connection\HttpClient;
use Hyperf\Qdrant\Struct\Collections\Enums\Distance;
use Hyperf\Qdrant\Struct\Collections\VectorParams;
use Hyperf\Qdrant\Struct\Points\ExtendedPointId;
use Hyperf\Qdrant\Struct\Points\Point\Record;
use Hyperf\Qdrant\Struct\Points\SearchCondition\FieldCondition;
use Hyperf\Qdrant\Struct\Points\SearchCondition\Filter;
use Hyperf\Qdrant\Struct\Points\SearchCondition\Match\MatchValue;
use Hyperf\Qdrant\Struct\Points\VectorStruct;
use Hyperf\Qdrant\Struct\Points\WithPayload;
use function Hyperf\Support\env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

function getClient(string $type = 'azure')
{
    switch ($type) {
        case 'openai':
            $openAI = new OpenAI();
            $config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'));
            $client = $openAI->getClient($config);
            break;
        case 'azure':
            $openAI = new AzureOpenAI();
            $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_HOST'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'));
            $client = $openAI->getClient($config);
            break;
        case 'rwkv':
            $rwkv = new Hyperf\Odin\Apis\RWKV\RWKV();
            $config = new RWKVConfig(env('RWKV_HOST'));
            $client = $rwkv->getClient($config);
            break;
        default:
            throw new \RuntimeException('Invalid type');
    }
    return $client;
}

$client = getClient('azure');
$conversionId = uniqid();

# 原始语料
$data = [
    '你是谁:你是一个由 Hyperf 组织开发的专业的数据分析机器人，你必须严格按照格式要求返回内容',
    '今天多少度:今天的气温是 20 度',
    'odin 是什么:Odin 是一个基于 PHP 的 LLM 应用开发框架',
];
$vectors = array_map(function (string $datum, int $key) use ($client) {
    return new Record(
        new ExtendedPointId($key + 10000),
        new VectorStruct($client->embedding($datum)->getData()[0]->embedding),
        ['mark' => 'payload 是自定义属性', 'prefix' => explode(':', $datum)[0], 'content' => explode(':', $datum)[1]],
    );
}, $data, array_keys($data));

# 创建 collection, 类似于数据库中的表
$collections = new Collections(new HttpClient(new Config()));
$collections->createCollection('test_collection', new VectorParams(1536, Distance::COSINE));
# 插入数据
$points = new Points(new HttpClient(new Config()));
$points->setWait(true);
$points->upsertPoints('test_collection', $vectors);
# 近似搜索
$result = $points->searchPoints(
    'test_collection',
    new VectorStruct($client->embedding('啥是Odin')->getData()[0]->embedding),
    2,
    withPayload: new WithPayload(true),
);
print_r($result);
# payload 过滤
$result = $points->searchPoints(
    'test_collection',
    new VectorStruct($client->embedding('你是Odin')->getData()[0]->embedding),
    3,
    new Filter(
        must: [
            new FieldCondition('prefix', new MatchValue('odin 是什么')),
        ]
    ),
    withPayload: new WithPayload(true),
);
print_r($result);

<?php

use Hyperf\Odin\Action\CalculatorAction;
use Hyperf\Odin\Action\SearchAction;
use Hyperf\Odin\Action\WeatherAction;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\RWKV\RWKVConfig;
use Hyperf\Odin\Conversation\Conversation;
use Hyperf\Odin\Memory\MessageHistory;
use function Hyperf\Support\env as env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

function getClient(string $type = 'azure')
{
    switch ($type) {
        case 'openai':
            $openAI = new OpenAI();
            $config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'),);
            $client = $openAI->getClient($config);
            break;
        case 'azure':
            $openAI = new AzureOpenAI();
            $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_HOST'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
            $client = $openAI->getClient($config);
            break;
        case 'rwkv':
            $rwkv = new Hyperf\Odin\Apis\RWKV\RWKV();
            $config = new RWKVConfig(env('RWKV_HOST'),);
            $client = $rwkv->getClient($config);
            break;
        default:
            throw new \RuntimeException('Invalid type');
    }
    return $client;
}

$client = getClient('azure');
$conversionId = uniqid();
$conversation = new Conversation();
$memory = new MessageHistory();

$input = '1+2=?，然后帮我查查东莞的明天的天气情况';
//$input = '东莞明天的最高多少度？以及 1+1=?，并将计算结果赋值给x用于下一次计算，x+10=?';
$response = $conversation->chat($client, $input, 'gpt-3.5-turbo', $conversionId, $memory, [
    new CalculatorAction(),
    new WeatherAction(),
    new SearchAction()
]);
echo PHP_EOL . PHP_EOL;
echo '[FINAL] AI: ' . $response;
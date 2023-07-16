<?php

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Conversation\Conversation;
use Hyperf\Odin\Memory\MessageHistory;
use function Hyperf\Support\env as env;

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

function getOpenAIClient(): OpenAIClient
{
    $openAI = new OpenAI();
    $config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'),);
    return $openAI->getClient($config);
}

function getAzureOpenAIClient(): AzureOpenAIClient
{
    $openAI = new AzureOpenAI();
    $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_ENDPOINT'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
    return $openAI->getClient($config);
}

$client = getAzureOpenAIClient();
$conversionId = uniqid();
$conversation = new Conversation();
$memory = new MessageHistory();

while (true) {
    echo 'Human: ';
    $input = trim(fgets(STDIN, 1024));
    $response = $conversation->chat($client, $input, 'gpt-3.5-turbo', $conversionId, $memory);
    echo 'AI: ' . $response . PHP_EOL;
}
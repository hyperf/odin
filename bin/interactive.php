<?php

use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Conversation\Conversation;
use Hyperf\Odin\Memory\MessageHistory;
use function Hyperf\Support\env as env;

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

$openAI = new OpenAI();
$config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'),);
$client = $openAI->getClient($config);
$conversionId = uniqid();
$conversation = new Conversation();
$memory = new MessageHistory();

// 从命令行获取输入，并循环调用 OpenAI 的 API
$handle = fopen("php://stdin", "r");
while (! feof($handle)) {
    echo 'Human: ';
    $input = trim(fread($handle, 1024));
    $response = $conversation->chat($client, $input, 'gpt-3.5-turbo', $conversionId, $memory);
    echo 'AI: ' . $response . PHP_EOL;
}
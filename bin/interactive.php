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

use Hyperf\Odin\Agent\ToolsAgent;
use Hyperf\Odin\Knowledge\Knowledge;
use Hyperf\Odin\Loader\Loader;
use Hyperf\Odin\Memory\MessageHistory;
use Hyperf\Odin\Model\EmbeddingInterface;
use Hyperf\Odin\ModelMapper;
use Hyperf\Odin\Observer;
use Hyperf\Odin\Prompt\OpenAIToolsAgentPrompt;
use Hyperf\Odin\Tool\TavilySearchResults;
use Hyperf\Odin\VectorStore\Qdrant\Config;
use Hyperf\Odin\VectorStore\Qdrant\Qdrant;
use Hyperf\Qdrant\Api\Collections;
use Hyperf\Qdrant\Api\Points;
use Hyperf\Qdrant\Connection\HttpClient;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

$enables = [
    'context_prompts' => true,
    'tool' => true,
    'knowledge' => false,
];

/** @var ModelMapper $modelMapper */
$modelMapper = $container->get(ModelMapper::class);
#$modelName = 'qwen:32b-chat';
$modelName = 'gpt-4-turbo';
$llm = $modelMapper->getModel($modelName);
$embeddingModel = $modelMapper->getModel($modelName);
$systemPrompt = file_get_contents('./magic2.prompt');
if ($enables['context_prompts']) {
    $contextPrompts = [
        'Current Time' => date('Y-m-d H:i:s'),
        'User Location' => '广东省深圳市福田区',
    ];
    $contextPrompts = array_reverse($contextPrompts);
    foreach ($contextPrompts as $key => $contextPrompt) {
        $systemPrompt = $key . ': ' . $contextPrompt . PHP_EOL . $systemPrompt;
    }
}
$prompt = new OpenAIToolsAgentPrompt($systemPrompt);
$tools = [];
if ($enables['tool']) {
    /** @var TavilySearchResults $tavilySearchResults */
    $tavilySearchResults = $container->get(TavilySearchResults::class);
    $tools = [
        $tavilySearchResults->setUseAnswerDirectly(false)->setSearchDepth('advanced'),
    ];
}
$observer = $container->get(Observer::class);
/** @var MessageHistory $memory */
$memory = $container->get(MessageHistory::class);
$conversationId = uniqid('agent_', true);
$knowledge = null;
if ($enables['knowledge']) {
    if (! $embeddingModel instanceof EmbeddingInterface) {
        throw new RuntimeException('Model must implement EmbeddingInterface');
    }
    $httpClient = new HttpClient(new Config());
    $points = new Points($httpClient);
    $collections = new Collections($httpClient);
    $knowledge = new Knowledge($embeddingModel, new Qdrant($points, $collections));
    $loader = new Loader();
    $document = $loader->loadMarkdownByFilePath('../data/技术中心-系统业务简述.md');
    $collectionName = 'knowledge';
    $knowledge->upsert($collectionName, $document);
}
$agent = new ToolsAgent(model: $llm, prompt: $prompt, memory: $memory, knowledge: $knowledge, observer: $observer, tools: $tools);
$defaultInputs = [];
while (true) {
    echo 'Human: ';
    // 如果 $defaultInputs 有值，就用 $defaultInputs 的值，否则就读取用户输入
    $input = array_shift($defaultInputs) ? : trim(fgets(STDIN));
    $isCommand = false;
    switch ($input) {
        case 'dump-messages':
            var_dump($memory->getConversations($conversationId));
            $isCommand = true;
            break;
        case 'enable-debug':
            $agent->setDebug(true);
            $isCommand = true;
            break;
        case 'disable-debug':
            $agent->setDebug(false);
            $isCommand = true;
            break;
    }
    if ($isCommand) {
        continue;
    }
    $response = $agent->invoke(['input' => $input], $conversationId);
    echo 'AI: ' . $response . PHP_EOL;
}

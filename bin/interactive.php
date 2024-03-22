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

use Hyperf\Odin\Agent\OpenAIToolsAgent;
use Hyperf\Odin\Memory\MessageHistory;
use Hyperf\Odin\ModelMapper;
use Hyperf\Odin\Observer;
use Hyperf\Odin\Prompt\OpenAIToolsAgentPrompt;
use Hyperf\Odin\Tools\TavilySearchResults;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

/** @var \Hyperf\Odin\ModelMapper $modelMapper */
$modelMapper = $container->get(ModelMapper::class);
$llm = $modelMapper->getDefaultModel();
$prompt = new OpenAIToolsAgentPrompt();
/** @var TavilySearchResults $tavilySearchResults */
$tavilySearchResults = $container->get(TavilySearchResults::class);
$tools = [
    $tavilySearchResults->setUseAnswerDirectly(false)->setSearchDepth('advanced'),
];
$observer = $container->get(Observer::class);
/** @var MessageHistory $memory */
$memory = $container->get(MessageHistory::class);
$conversationId = uniqid('agent_', true);
$agent = new OpenAIToolsAgent(model: $llm, prompt: $prompt, memory: $memory, observer: $observer, tools: $tools);
while (true) {
    echo 'Human: ';
    $input = trim(fgets(STDIN, 1024));
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

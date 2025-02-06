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
use Hyperf\Odin\Conversation\Conversation;
use Hyperf\Odin\Conversation\Option;
use Hyperf\Odin\Interpreter\CodeRunner;
use Hyperf\Odin\Memory\MessageHistory;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Prompt\Prompt;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

/** @var Conversation $conversation */
$conversation = $container->get(Conversation::class);
$conversation = $conversation->withMemory(new MessageHistory());
$systemPrompt = new SystemMessage(Prompt::getPrompt('CodeInterpreter', [
    'name' => get_current_user(),
    'working_dir' => getcwd(),
    'os' => PHP_OS_FAMILY,
]));
$conversationId = $conversation->createConversationId();
while (true) {
    echo 'Human: ';
    $input = trim(fgets(STDIN, 1024));
    $response = $conversation->chat(messages: [
        $systemPrompt,
        new UserMessage($input),
    ], model: 'gpt-4', option: new Option(temperature: 0, maxTokens: 3000, functions: [CodeRunner::toFunctionCallDefinition()]), conversationId: $conversationId);
    echo 'AI: ' . $response . PHP_EOL;
}

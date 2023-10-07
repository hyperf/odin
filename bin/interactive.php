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

use Hyperf\Odin\Conversation\Option;
use Hyperf\Odin\Memory\MessageHistory;
use Hyperf\Odin\Prompt\Prompt;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

$llm = $container->get(\Hyperf\Odin\LLM::class);
$conversation = $llm->createConversation()->generateConversationId()->withMemory(new MessageHistory());
while (true) {
    echo 'Human: ';
    $input = trim(fgets(STDIN, 1024));
    $response = $conversation->chat(Prompt::input($input), '', new Option());
    echo 'AI: ' . $response . PHP_EOL;
}

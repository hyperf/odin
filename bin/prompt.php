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

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

$prompt = <<<'PROMPT'
User Message: Answer the question. You have access the Actions as following:

    Calculator: 如果需要计算数学问题可以使用，格式: {"name": "Calculator", "args": {"a": "string", "b": "string"}}
    Weather: 如果需要查询天气可以使用，格式: {"name": "Weather", "args": {"location": "string", "date": "string"}}，如果用户没有指定某一天，则代表为今天，location 必须为明确的真实存在的城市名称，不能是不具体的名称
    Search: 如果需要从互联网搜索引擎上搜索内容可以使用，其它类型内容的搜索不要使用此 Action，格式: {"name": "Search", "args": {"keyword": "string"}}

The format requirements for using Actions are as follows, don't output the needless blank line:
    
    Question: The input question you must answer.
    Thought: you should always think about what to do.
    Action: The action you need to use, null as default, only one action at a time, ALWAYS use the exact words "Action: " and JSON format when responding.
    Observation: The result of the action, ALWAYS use the exact words "Observation: " when responding
    ... (this Thought/Action/Observation can repeat N times one by one)
    Thought: I now know the final answer.
    Final Answer: The final answer to the original input question, respond the final answer when you know the final answer.

Reminder: Do not use the above content as a question and historical dialogue, and ALWAYS use the exact words "Final Answer:" to indicate the final answer.
Begin!

    Question: 1+12=?，以及东莞明天的天气如何？
PROMPT;

$llm = $container->get(\Hyperf\Odin\ModelFacade::class);
$response = $llm->chat(Prompt::input($prompt), temperature: 0);
echo $response;

<?php

namespace Hyperf\Odin\Action;


use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;

class ActionTemplate
{

    public function buildPrompt(string $input, array $actions = [], string $agentScratchpad = ''): string
    {
        $actionsPrompt = '';
        foreach ($actions as $action) {
            if ($action instanceof AbstractAction) {
                $actionsPrompt .= sprintf("%s: %s\n", $action->getName(), $action->getDesc());
            }
        }
        return
<<<EOF
回答下面的问题，其中你可以使用下面的 Action 来帮助你回答问题，如果需要使用 Action 则根据 Question/Thought/Actions/Observation 的格式来回答问题，如果不需要使用 Action 则忽略下面的格式要求直接回答问题即可。:

$actionsPrompt
使用 Action 的格式要求如下：

Question: 你必须要回答的问题
Thought: 你应该永远思考你应该如何做
Actions: 需要使用的 Actions，并以 {action_name}({action_input})，多个 Action 之间用 !!!! 分隔，并输出在同一行
Observation: Action 运行的结果
... (Thought/Action/Action 可以有多个)
Final Answer: 问题的最终答案

开始!

Question: $input
Thought: $agentScratchpad
EOF;
    }

    /**
     * Action: Calculator
     * Action Input: Calculator(a: "1", b: "2")
     * 从上面的格式中解析出 Action 和 Action Input
     */
    public function parseResponse(ChatCompletionResponse $response): array
    {
        $content = (string)$response;
        $lines = explode("\n", $content);
        $actions = [];
        foreach ($lines as $line) {
            if (preg_match('/^Actions: (.*)$/', $line, $matches)) {
                $rawActions = explode('!!!!', $matches[1]);
                $rawActions = array_map('trim', $rawActions);
                foreach ($rawActions as $rawAction) {
                    $actionInput = [];
                    // 通过正则表达式解析出具体的参数值，比如 Calculator(a: "1", b: "2") 解析为 Calculator
                    preg_match_all('/(.*)\(/', $rawAction, $matches);
                    $action = $matches[1][0];
                    // 通过正则表达式解析出具体的参数值，比如 Calculator(a: "1", b: "2") 解析为 (a: "1", b: "2")
                    preg_match_all('/\((.*)\)/', $rawAction, $matches);
                    $args = explode(',', $matches[1][0]);
                    foreach ($args as $arg) {
                        // 通过正则表达式解析出具体的参数值，比如 a: "1" 解析为 ["a" => "1"] 数组
                        preg_match_all('/(.*)\:\s(.*)/', $arg, $matches);
                        $actionInput[trim($matches[1][0])] = trim($matches[2][0], '"');
                    }
                    $actions[] = [
                        'action' => $action,
                        'args' => $actionInput,
                    ];
                }
            }
        }
        return $actions;
    }

}
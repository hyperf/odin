<?php

namespace Hyperf\Odin\Action;


use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;

class ActionTemplate
{

    public function buildAfterActionExecutedPrompt(string $input, array $actionsResults): string
    {
        $resultPrompt = '';
        $actionNameStr = implode(', ', array_keys($actionsResults));
        foreach ($actionsResults as $actionName => $result) {
            $resultPrompt .= sprintf("%s: %s\n", $actionName, $result);
        }
        return
<<<EOF
你已经使用了 $actionNameStr Actions，并运行得到了下面的运行结果:

$resultPrompt

你需要根据上面的运行结果以及用户的 Question 来回答问题。

开始!

Question: $input
Answer: 
EOF;
    }

    public function buildThoughtActionsPrompt(string $input, array $actions = [], string $agentScratchpad = ''): string
    {
        $actionsPrompt = '';
        foreach ($actions as $action) {
            if ($action instanceof AbstractAction) {
                $actionsPrompt .= sprintf("%s: %s\n    ", $action->getName(), $action->getDesc());
            }
        }
        return
<<<EOF
回答用户的问题，其中你可以使用下面的 Action 来帮助你回答问题，如果需要使用 Action 则根据 Question/Thought/Actions/Observation 的格式来回答问题，如果不需要使用 Action 则返回 Action: None，不需要回答用户提出的问题的答案:

    $actionsPrompt
    使用 Action 的格式要求如下：
    
    Question: 你必须要回答的问题
    Actions: 需要使用的 Actions，并以 {action_name}({action_input})，多个 Action 之间用 @@@@ 分隔，并输出在同一行，比如 Actions: Calculator(a: "1", b: "2")@@@@Weather(city: "北京")
    
    开始!
    
    Question: $input
EOF;
    }

    /**
     * Action: Calculator
     * Action Input: Calculator(a: "1", b: "2")
     * 从上面的格式中解析出 Action 和 Action Input
     */
    public function parseActions(ChatCompletionResponse $response): array
    {
        $content = (string)$response;
        $lines = explode("\n", $content);
        $actions = [];
        foreach ($lines as $line) {
            if (preg_match('/^Actions: (.*)$/', $line, $matches)) {
                $rawActions = explode('@@@@', $matches[1]);
                $rawActions = array_map('trim', $rawActions);
                foreach ($rawActions as $rawAction) {
                    $actionInput = [];
                    // 通过正则表达式解析出具体的参数值，比如 Calculator(a: "1", b: "2") 解析为 Calculator
                    preg_match_all('/(.*)\(/', $rawAction, $matches);
                    if (! isset($matches[1][0])) {
                        continue;
                    }
                    $action = $matches[1][0];
                    // 通过正则表达式解析出具体的参数值，比如 Calculator(a: "1", b: "2") 解析为 (a: "1", b: "2")
                    preg_match_all('/\((.*)\)/', $rawAction, $matches);
                    $args = explode(',', $matches[1][0]);
                    foreach ($args as $arg) {
                        // 通过正则表达式解析出具体的参数值，比如 a: "1" 解析为 ["a" => "1"] 数组
                        preg_match_all('/(.*)\:\s(.*)/', $arg, $matches);
                        if (isset($matches[1][0]) && isset($matches[2][0])) {
                            $actionInput[trim($matches[1][0])] = trim($matches[2][0], '"');
                        } else {
                            $actionInput[] = trim($arg);
                        }
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
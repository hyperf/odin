<?php

namespace Hyperf\Odin\Action;


use Hyperf\Codec\Exception\InvalidArgumentException;
use Hyperf\Codec\Json;
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
        return <<<EOF
你已经使用了 $actionNameStr Actions，并运行得到了下面的运行结果:

$resultPrompt

你需要根据历史对话记录和上面的运行结果来回答用户提出的 Question。

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
        return <<<EOF
回答用户的问题，其中你可以使用下面的 Action 来帮助你回答问题，如果需要使用 Action 则根据 Question/Actions 的格式来回答问题，如果不需要使用 Action 则返回 Action: []，不需要回答用户提出的问题的答案:

    $actionsPrompt
    使用 Action 的格式要求如下：
    
    Question: 你必须要回答的问题
    Actions: 需要使用的 Actions，并以 JSON 格式输出，格式：[{"name":"Action 名称","args":{"Action 参数 key":"Action 参数 value"}}]，比如 [{"name":"Calculator","args":{"a":"1","b":"2"}},{"name":"Weather","args":{"city":"北京"}}]
    
    不要使用上面的内容作为问题和历史对话。
    开始!
    
    Question: $input
EOF;
    }

    public function parseActions(ChatCompletionResponse $response): array
    {
        $content = (string)$response;
        $lines = explode("\n", $content);
        $actions = [];
        foreach ($lines as $line) {
            if (preg_match('/^Actions: (.*)$/', $line, $matches)) {
                try {
                    $rawActions = Json::decode($matches[1]);
                } catch (InvalidArgumentException $exception) {
                    $rawActions = [];
                }
                foreach ($rawActions as $rawAction) {
                    if (isset($rawAction['name'])) {
                        $actionName = $rawAction['name'];
                        $actionArgs = $rawAction['args'] ?? [];
                        $actions[] = [
                            'name' => trim($actionName),
                            'args' => $actionArgs,
                        ];
                    }
                }
            }
        }
        return $actions;
    }

}
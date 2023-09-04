<?php

namespace Hyperf\Odin\Prompt;


use Hyperf\Odin\Action\AbstractAction;

class AgentPromptTemplate extends AbstractPromptTemplate
{

    public function build(string $input, string $agentThoughtAndObservation, array $actions): string
    {
        return <<<EOF
Answer the question. You have access the Actions as following:

    {$this->buildActionsListPrompt($actions)}

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

    Question: {$input}
{$agentThoughtAndObservation}

EOF;
    }

    public function buildActionsListPrompt(array $actions): string
    {
        $prompt = '';
        foreach ($actions as $action) {
            if ($action instanceof AbstractAction) {
                $prompt .= sprintf("%s: %s\n    ", $action->getName(), $action->getDesc());
            }
        }
        return rtrim($prompt);
    }

}
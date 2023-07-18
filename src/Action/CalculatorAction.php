<?php

namespace Hyperf\Odin\Action;


class CalculatorAction extends AbstractAction
{

    public string $name = 'Calculator';
    public string $desc = 'If user want to calculate math, use this action, action input format: Calculator(a: string, b: string)';

    public function handle(string $a, string $b): string
    {
        echo 'Enter calculator action' . PHP_EOL;
        return bcadd($a, $b);
    }

}
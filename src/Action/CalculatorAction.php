<?php

namespace Hyperf\Odin\Action;


class CalculatorAction extends AbstractAction
{

    public string $name = 'Calculator';
    public string $desc = '如果需要计算数学问题可以使用，格式: {"name": "Calculator", "args": {"a": "string", "b": "string"}}';

    public function handle(string $a, string $b): string
    {
        $a = trim($a, ' \t\n\r\0\x0B\'"');
        $b = trim($b, ' \t\n\r\0\x0B\'"');
        $result = bcadd($a, $b);
        return sprintf('计算结果：%s + %s = %s', $a, $b, $result);
    }

}
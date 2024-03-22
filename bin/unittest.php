<?php

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

$container = require_once dirname(dirname(__FILE__)) . '/bin/init.php';

$code = '
namespace Hyperf\Odin\Action;


class CalculatorAction extends AbstractAction
{

    public string $name = \'Calculator\';
    public string $desc = \'如果需要计算数学问题可以使用，格式: Calculator(a: string, b: string)\';

    public function handle(string $a, string $b): string
    {
        $a = trim($a, \' \t\n\r\0\x0B\'"\');
        $b = trim($b, \' \t\n\r\0\x0B\'"\');
        $result = bcadd($a, $b);
        return sprintf(\'%s + %s = %s\', $a, $b, $result);
    }
}
';
$prompt = <<<PROMPT
The following is a piece of PHP code. You need to analyze this code and generate the corresponding complete unit test code based on the code and logic.
You should first analyze which unit tests should be generated, and generate the corresponding unit test codes respectively, through the PHPUnit testing framework.
Do not explain your analysis logic, just generate the corresponding unit test code. The relevant explanations can be written in the form of comments into the corresponding unit test method.

code:
```php
$code
```
PROMPT;

$llm = $container->get(\Hyperf\Odin\ModelFacade::class);

echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('You are a unit test generation robot developed by the Hyperf organization. You must return content strictly in accordance with the format requirements.'),
        'user' => new UserMessage($prompt),
    ]) . PHP_EOL;

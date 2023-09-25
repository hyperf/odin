<?php

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

function chat(string $message): string
{
    $container = ApplicationContext::getContainer();
    $llm = $container->get(\Hyperf\Odin\LLM::class);
    $result = $llm->chat([
            'system' => new SystemMessage('You are a low-code generator developed by Hyperf. Follow the format requirements to return content.'),
            'user' => new UserMessage($message),
        ], temperature: 0) . PHP_EOL;
    echo '[AI]: ' . $result;
    return $result;
}


$json = <<<JSON
[
    'name' => 'run_code',
    'description' => 'Executes code and returns the output.',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'description' => 'The programming language, use php first.',
                'enum' => [
                    'php',
                    'python',
                    'shell'
                ]
            ],
            'code' => [
                'type' => 'string',
                'description' => 'The code to execute'
            ]
        ],
        'required' => [
            'language',
            'code'
        ]
    ]
]
JSON;

// ARRAY/JSON 转为 PHP 对象的 Prompt
$prompt = <<<PROMPT
Requirements: Transform PHP Array / JSON to PHP object, the object should be an clean object without any property value, should not includes the value, all object class should includes setter and getter, and strong type, output the PHP object code directly, no need to provide the use cases.
ClassName: FunctionCallDefinition, FunctionCallParameters, FunctionCallParameter
Array: ```array
$json
```
Output Format: Put the code into ```php ``` tag, and output the code directly.
Output:
PROMPT;

$result = chat($prompt);
var_dump($result);
exit();
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

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use function Hyperf\Support\env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{
    public string $model = 'gpt-3.5-turbo';

    public function chat(array $messages, float $temperature = 0.9): string
    {
        $client = $this->getAzureOpenAIClient();
        $client->setDebug(false);
        return $client->chat($messages, $this->model, $temperature, 3000);
    }

    public function getOpenAIClient(): OpenAIClient
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig(env('OPENAI_API_KEY'));
        return $openAI->getClient($config);
    }

    public function getAzureOpenAIClient(): AzureOpenAIClient
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY'), baseUrl: env('AZURE_OPENAI_API_BASE'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'));
        return $openAI->getClient($config);
    }
}

function chat(string $message): string
{
    $prefixPrompt = <<<'PROMPT'
You are a code generator for a low-code platform. The project uses Hyperf 3.0 framework for code implementation. You need to analyze the process in detail and generate complete and runnable code. The result must be returned according to the required format.
PROMPT;

    $llm = new LLM();
    $result = $llm->chat([
            'system' => new SystemMessage('You are a low-code generator developed by Hyperf. Follow the format requirements to return content.'),
            'user' => new UserMessage($prefixPrompt . PHP_EOL . $message),
        ], temperature: 0) . PHP_EOL;
    echo '[AI]: ' . $result;
    return $result;
}

$userMessage = '我需要设计一个访客管理系统，需要创建一个表单用于满足访客申请的表单，需要收集访客的姓名、手机号码、身份证号码、来访时间、来访的团队名称等基础信息，如果访客有开车过来，还需要提供车牌';

/**
 * Code structure generation.
 */
$analyse = <<<PROMPT
User Demand：{$userMessage}
Requirements: Analyze user needs and design code structure to meet the needs. Code structure should be simple, clear, and without redundancy. Use full namespace for class calls. Code structure must comply with Hyperf 3.0 framework rules. No need to output code or extra line breaks, just output code structure according to format requirements. A request usually includes Controller, Service, Model, Repository, FormRequest. A class should have all methods.
Format:
[
    {
        "class": "the class name",
        "namespace": "the namespace of the class",
        "path": "the path of the class, including file name and php extension",
        "desc": "the description of class, describe the purpose of class and the usage of class",
        "methods": [
            {
                "name": "the method name",
                "params": [
                    {
                        "name": "the param name",
                        "type": "the param type"
                    }
                ],
                "return_type": "the return type of method",
                "desc": "the description of method, describe the purpose of method and the usage of method"
            }
        ]
    }
]
Result：
PROMPT;

$result = chat($analyse);
$structs = json_decode(trim($result), true);

$promptStructs = [];
foreach ($structs ?? [] as $struct) {
    if (! isset($struct['class']) || ! isset($struct['methods']) || ! isset($struct['desc']) || ! isset($struct['path']) || ! isset($struct['namespace'])) {
        continue;
    }
    $text = "{$struct['namespace']}\\{$struct['class']}: {$struct['desc']}" . PHP_EOL;
    foreach ($struct['methods'] ?? [] as $method) {
        if (! isset($method['name']) || ! isset($method['params']) || ! isset($method['return_type']) || ! isset($method['desc'])) {
            continue;
        }
        $paramsText = '';
        foreach ($method['params'] ?? [] as $param) {
            $paramsText .= "{$param['type']} {$param['name']}, ";
        }
        $paramsText = rtrim($paramsText, ', ');
        $text .= "- {$method['name']}({$paramsText}): {$method['return_type']} // {$method['desc']}" . PHP_EOL;
    }
    $promptStructs[] = $text;
}
$promptStruct = implode(PHP_EOL, $promptStructs);

/**
 * Data structure generation.
 */
$dataStruct = '';
$dataStructPrompt = <<<PROMPT
User requirements: {$userMessage}
Requirements: Generate the data structure that meets the user requirements. The data structure should be simple, clear, and without redundancy. Data structure is the key structure to realize user requirements. No extra line breaks, just output data structure according to format requirements.
Format:
    DataModelName:
    - Property: Type // Description
    (this DataModel can repeat N times if necessary)
Data structure:
PROMPT;
$dataStruct = chat($dataStructPrompt);

/**
 * Code generation.
 */
$outputDir = BASE_PATH . '/output';
$codeContext = '';
foreach ($structs as $struct) {
    if (! isset($struct['class']) || ! isset($struct['methods']) || ! isset($struct['desc'])) {
        continue;
    }
    $generate = <<<PROMPT
User requirements: {$userMessage}
data structure:
{$dataStruct}
code structure:
{$promptStruct}
Requirements: Generate runnable detailed PHP code for `{$struct['class']}` class based on code structure and user requirements. Only output code for `{$struct['class']}` class, code structure must comply with Hyperf 3.0 framework rules. Use Camel case for class names, class properties, and method names. Use Snake case for array keys. Implement strong typing. The code implementation must be runnable specific code and must implementation all logic, cannot be omitted, and cannot only have comments, strictly follow the return type. No need for explanations or Note or extra line breaks, just output the code, make sure the code is runnable.
Result:
PROMPT;
    $result = chat($generate);
    $code = trim($result);
    if (str_starts_with($code, '```php')) {
        // 解析 ```php ``` 之间的代码
        preg_match('/```php(.*)```/s', $code, $matches);
        $code = trim($matches[1] ?? '');
    }
    // 生成文件夹
    $dir = dirname($struct['path']);
    if (! is_dir($outputDir . '/' . $dir)) {
        // 从 path 中解析出文件夹
        mkdir($outputDir . '/' . $dir, 0777, true);
    }
    file_put_contents($outputDir . '/' . $struct['path'], $code);
    $codeContext .= '// Class: ' . $struct['path'] . PHP_EOL;
    $codeContext .= $code . PHP_EOL;
}

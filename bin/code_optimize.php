<?php

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use function Hyperf\Support\env as env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{

    public string $model = 'gpt-3.5-turbo';

    public function chat(array $messages, float $temperature = 0.9,): string
    {
        $client = $this->getAzureOpenAIClient();
        $client->setDebug(false);
        return $client->chat($messages, $this->model, $temperature);
    }

    function getOpenAIClient(): OpenAIClient
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'),);
        return $openAI->getClient($config);
    }

    function getAzureOpenAIClient(): AzureOpenAIClient
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_HOST'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
        return $openAI->getClient($config);
    }
}

function chat(string $message): string
{
    $prefixPrompt = <<<PROMPT
你是一个低代码平台的代码生成器，项目使用 Hyperf 3.0 框架作为代码实现，你需要尽可能详细的分析流程，代码内容不能省略必须完成实现可运行的具体的代码，结果必须根据格式要求返回。
PROMPT;

    $llm = new LLM();
    $result = $llm->chat([
            'system' => new SystemMessage('你是一个由 Hyperf 组织开发的低代码生成器，你必须严格按照格式要求返回内容'),
            'user' => new UserMessage($prefixPrompt . PHP_EOL . $message),
        ], temperature: 0) . PHP_EOL;
    echo '[AI]: ' . $result;
    return $result;
}

$userMessage = "根据代码逻辑和注释要求，改成 TypeScript 代码，每个方法以 JsDoc 的形式生成注释，实现方法中具体的业务逻辑，不能只输出注释，必须完成所有的 TODO，必须是具体的、完整的、可运行的代码";

$outputDir = BASE_PATH . '/output';
$sourceCodeFilePath = $outputDir . '/service.js';
$sourceCode = file_get_contents($sourceCodeFilePath);
$generate = <<<PROMPT
用户需求：$userMessage
原始代码：
$sourceCode
要求：你需要根据上面的代码结构和用户的需求，对提供的代码进行修改，不需要输出其他类的代码，代码结构必须符合 Javascript 的规范，使用强类型代码实现，不需要任何解释和多余的换行，直接输出代码即可。
返回结果：
PROMPT;
var_dump($generate);
exit();
$result = chat($generate);
$code = trim($result);
// 解析 ```php ``` 之间的代码
preg_match('/```php(.*)```/s', $code, $matches);
$code = trim($matches[1] ?? '');
file_put_contents(str_replace('service.js', 'service.ts', $sourceCodeFilePath), $code);



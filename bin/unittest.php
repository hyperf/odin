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
以下是一段 PHP 代码，你需要分析这段代码并根据代码和逻辑生成对应的完整的单元测试代码。
你应该先分析应该生成哪些单元测试，并分别生成对应的单元测试的代码，通过 PHPUnit 测试框架实现。
不要解释你的分析逻辑，只需要生成对应的单元测试代码即可，相关解释可以以注释形式写到对应的单元测试方法中。

代码如下：
```php
$code
```
PROMPT;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{

    public string $model = 'gpt-3.5-turbo';

    public function chat(array $messages, float $temperature = 0.1,): string
    {
        $client = $this->getAzureOpenAIClient();
        $client->setDebug(true);
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

$llm = new LLM();

echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('你是一个由 Hyperf 组织开发的单元测试生成机器人，你必须严格按照格式要求返回内容'),
        'user' => new UserMessage($prompt),
    ]) . PHP_EOL;

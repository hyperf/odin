<?php
$prompt = <<<PROMPT
以下是一段用户与AI的对话记录：

用户: 1+12=?，以及东莞明天的天气如何？
AI: 1+12=13，东莞明天的天气预报为：最高温度34℃，最低温度27℃，白天天气多云，晚上天气多云。
用户: 我刚才询问天气的是哪个城市？
AI: 您刚才询问的是东莞的天气。
用户: 能见度如何？
AI: 请问您是在询问东莞的能见度吗？

用户: 是的
AI: 
PROMPT;

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use function Hyperf\Support\env as env;

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM {

    public string $model = 'gpt-3.5-turbo';
    public function chat(array $messages, float $temperature = 0.9,): string
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
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_ENDPOINT'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
        return $openAI->getClient($config);
    }
}

$llm = new LLM();

echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('你是一个由 Hyperf 组织开发的聊天机器人，你必须严格按照格式要求返回内容'),
        'user' => new UserMessage($prompt),
    ]) . PHP_EOL;

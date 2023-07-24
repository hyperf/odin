<?php
$prompt = <<<PROMPT
用户: 东莞明天的最高多少度？以及 1+1=?，并将计算结果赋值给x用于下一次计算，x+10=?
AI: 
PROMPT;

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\RWKV\RWKVConfig;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use function Hyperf\Support\env as env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{

    public string $model = 'gpt-3.5-turbo';

    public function chat(array $messages, float $temperature = 0.9, string $llmType = 'azure'): string
    {
        $client = $this->getClient($llmType);
        $client->setDebug(true);
        return $client->chat($messages, $this->model, $temperature);
    }

    public function getClient(string $type = 'azure')
    {
        switch ($type) {
            case 'openai':
                $openAI = new OpenAI();
                $config = new OpenAIConfig(env('OPENAI_API_KEY_FOR_TEST'),);
                $client = $openAI->getClient($config);
                break;
            case 'azure':
                $openAI = new AzureOpenAI();
                $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY_FOR_TEST'), baseUrl: env('AZURE_OPENAI_HOST'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
                $client = $openAI->getClient($config);
                break;
            case 'rwkv':
                $rwkv = new Hyperf\Odin\Apis\RWKV\RWKV();
                $config = new RWKVConfig(env('RWKV_HOST'),);
                $client = $rwkv->getClient($config);
                break;
            default:
                throw new \RuntimeException('Invalid type');
        }
        return $client;
    }

}

$llm = new LLM();

echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('你是一个由 Hyperf 组织开发的聊天机器人，你必须严格按照格式要求返回内容'),
        'user' => new UserMessage($prompt),
    ], llmType: 'azure') . PHP_EOL;

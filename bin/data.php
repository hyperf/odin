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

$data = file_get_contents(BASE_PATH . '/data/销售额趋势.csv');

$prompt = <<<PROMPT
你是一个专业的数据分析师，你需要根据下面的数据进行分析，可以通过数学统计、归因分析、关联性分析、趋势分析等专业的分析技巧，根据用户问题以结论性的内容简洁的输出你的分析结果，不需要解释计算过程，尽量不要输出空白行：

数据：
$data

问题：进行数据分析
分析结果：
PROMPT;

$llm = new LLM();
echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('你是一个由 Hyperf 组织开发的专业的数据分析机器人，你必须严格按照格式要求返回内容'),
        'user' => new UserMessage($prompt),
    ]) . PHP_EOL;

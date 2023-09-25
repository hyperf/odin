<?php
$prompt = <<<PROMPT
User Message: Answer the question. You have access the Actions as following:

    Calculator: 如果需要计算数学问题可以使用，格式: {"name": "Calculator", "args": {"a": "string", "b": "string"}}
    Weather: 如果需要查询天气可以使用，格式: {"name": "Weather", "args": {"location": "string", "date": "string"}}，如果用户没有指定某一天，则代表为今天，location 必须为明确的真实存在的城市名称，不能是不具体的名称
    Search: 如果需要从互联网搜索引擎上搜索内容可以使用，其它类型内容的搜索不要使用此 Action，格式: {"name": "Search", "args": {"keyword": "string"}}

The format requirements for using Actions are as follows, don't output the needless blank line:
    
    Question: The input question you must answer.
    Thought: you should always think about what to do.
    Action: The action you need to use, null as default, only one action at a time, ALWAYS use the exact words "Action: " and JSON format when responding.
    Observation: The result of the action, ALWAYS use the exact words "Observation: " when responding
    ... (this Thought/Action/Observation can repeat N times one by one)
    Thought: I now know the final answer.
    Final Answer: The final answer to the original input question, respond the final answer when you know the final answer.

Reminder: Do not use the above content as a question and historical dialogue, and ALWAYS use the exact words "Final Answer:" to indicate the final answer.
Begin!

    Question: 1+12=?，以及东莞明天的天气如何？
    Thought: The question requires two actions: Calculator to calculate 1+12 and Weather to check the weather in Dongguan tomorrow.
    Action: {"name": "Calculator", "args": {"a": "1", "b": "12"}}
    Observation: 1 + 12 = 13
    Thought: Now that I have calculated 1+12 and obtained the result of 13, I can proceed to check the weather in Dongguan tomorrow.
    Action: {"name": "Weather", "args": {"location": "Dongguan", "date": "tomorrow"}}
    Observation: {"weathers":["日期: 2023-07-24, 日出: 05:52, 日落: 19:12, 月升: 11:08, 月落: 23:13, 月相: 峨眉月, 最高温度: 36℃, 最低温度: 27℃, 白天天气: 晴, 晚上天气: 多云, 白天风向: 北风, 晚上风向: 北风, 白天风力: 1-2, 晚上风力: 1-2, 白天风速: 3, 晚上风速: 3, 相对湿度: 74, 降水量: 0.0, 紫外线指数: 9, 能见度: 24","日期: 2023-07-25, 日出: 05:52, 日落: 19:12, 月升: 12:00, 月落: 23:46, 月相: 峨眉月, 最高温度: 36℃, 最低温度: 27℃, 白天天气: 晴, 晚上天气: 多云, 白天风向: 北风, 晚上风向: 北风, 白天风力: 1-2, 晚上风力: 1-2, 白天风速: 3, 晚上风速: 3, 相对湿度: 69, 降水量: 0.0, 紫外线指数: 6, 能见度: 22","日期: 2023-07-26, 日出: 05:53, 日落: 19:12, 月升: 12:56, 月落: , 月相: 上弦月, 最高温度: 37℃, 最低温度: 28℃, 白天天气: 晴, 晚上天气: 多云, 白天风向: 北风, 晚上风向: 北风, 白天风力: 1-2, 晚上风力: 1-2, 白天风速: 3, 晚上风速: 3, 相对湿度: 67, 降水量: 0.0, 紫外线指数: 6, 能见度: 24"],"note":"以上是东莞未来三天的天气预报，今天是2023-07-24，需要根据用户的需求简洁的返回对应的天气预报情况，返回核心指标即可，不需要返回所有数据"}
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
                $config = new OpenAIConfig(env('OPENAI_API_KEY'),);
                $client = $openAI->getClient($config);
                break;
            case 'azure':
                $openAI = new AzureOpenAI();
                $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY'), baseUrl: env('AZURE_OPENAI_API_BASE'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
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
        'system' => new SystemMessage('You are a robot developed by the Hyperf organization, you must return content in strict format requirements.'),
        'user' => new UserMessage($prompt),
    ], temperature: 0, llmType: 'azure') . PHP_EOL;

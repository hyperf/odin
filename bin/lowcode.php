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

$prompt = <<<PROMPT
你是一个低代码表单生成器，你需要分析用户需求并输出你的对应的表单设计，你不需要解释思考过程，结果直接以 JSON 格式返回，你必须遵循下方的 JSON 格式要求：

格式要求：
{
    "forms": [
        {
            "name" : "string 类型，表单控件显示的名称",
            "type" : "enum 类型，表单控件类型，支持 input, datetime, radio, checkbox, select",
            "required": "bool 类型，表单控件是否必填，支持 true, false，除非我明确告诉你必填，其余情况你都需要根据用户需求判断是否应该必填",
            "validate": "string 类型，表单控件的校验规则，支持正则表达式，除非我明确告诉你需要校验，其余情况你都需要根据用户需求判断是否需要校验，不需要校验时不需要返回该字段",
            "options": "list 类型，表单控件的选项，仅当 type 为 radio, checkbox, select 时有效",
        }
    ] 
}

问题：设计一个企业的访客管理系统中用于满足访客申请的表单。
生成的表单 JSON：
PROMPT;

$llm = new LLM();
echo '[AI]: ' . $llm->chat([
        'system' => new SystemMessage('你是一个由 Hyperf 组织开发的低代码生成器，你必须严格按照格式要求返回内容'),
        'user' => new UserMessage($prompt),
    ], temperature: 0) . PHP_EOL;

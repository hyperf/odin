<?php

use Hyperf\Odin\Action\CalculatorAction;
use Hyperf\Odin\Action\SearchAction;
use Hyperf\Odin\Action\WeatherAction;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\RWKV\RWKVConfig;
use Hyperf\Odin\Conversation\Conversation;
use Hyperf\Odin\Memory\MessageHistory;
use function Hyperf\Support\env as env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{

    public Conversation $conversation;
    public Hyperf\Odin\Memory\AbstractMemory $memory;
    public array $actions = [];
    public string $model = 'gpt-3.5-turbo';

    public function __construct(protected bool $debug = false)
    {
        $this->conversation = new Conversation();
        $this->memory = new MessageHistory();
        $this->actions = [new CalculatorAction(), new WeatherAction(), new SearchAction()];
    }

    public function chat(string $input, string $conversionId, string $llmType = 'azure'): string
    {
        $client = $this->getClient($llmType);
        $client->setDebug($this->debug);
        return $this->conversation->chat($client, $input, $this->model, $conversionId, $this->memory, $this->actions);
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

$llm = new LLM(true);

$inputs = [
    '1+12=?，以及东莞明天的天气如何？',
    '我刚才询问天气的是哪个城市？',
    '能见度如何？',
    '12加上22等于多少',
    '我都询问过哪些数学计算，列出所有',
];

$conversionId = uniqid();

foreach ($inputs as $input) {
    echo '[Human]: ' . $input . PHP_EOL;
    echo '[AI]: ' . $llm->chat($input, $conversionId, llmType: 'azure') . PHP_EOL;
}
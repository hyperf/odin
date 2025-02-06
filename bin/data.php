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
use Hyperf\Di\ClassLoader;
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

ClassLoader::init();

class LLM
{
    public string $model = 'gpt-3.5-turbo';

    public function chat(array $messages, float $temperature = 0): string
    {
        $client = $this->getAzureOpenAIClient();
        $client->setDebug(true);
        return $client->chat($messages, $this->model, $temperature);
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

$data = file_get_contents(BASE_PATH . '/data/test.markdown');

$prompt = <<<PROMPT
你是一个专业的数据分析师，你需要根据下面的数据进行分析，根据用户问题以结论性的内容简洁的输出你的分析结果，尽量不要输出空白行：

数据：
{$data}

数据计算逻辑：单杯利润=价格-费用合计，毛利率=毛利/价格，毛利=价格-物料成本，费用合计=运营费用+营销费用+其它成本+折旧+管理费用+税项
要求：严格基于上面的数据和数据计算逻辑，一步一步推理全过程回答下面的问题
问题：如果我想在2021年提高单杯利润到2.1，但前提保持价格为15.1不变，那么其它指标应该要如何调整才能支持这个目标？调整多少？
分析结果：
PROMPT;

$llm = new LLM();
echo '[AI]: ' . $llm->chat([
    'system' => new SystemMessage('你是一个由 Hyperf 组织开发的专业的数据分析机器人，你必须严格按照格式要求返回内容'),
    'user' => new UserMessage($prompt),
]) . PHP_EOL;

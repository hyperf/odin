<?php

namespace HyperfTest\Odin\Cases\Apis;

use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\AzureOpenAI\Client;
use Hyperf\Odin\Exception\NotImplementedException;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;
use function Hyperf\Support\env as env;

class AzureOpenAITest extends AbstractTestCase
{

    public function testGetClient()
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig($apiKey = 'sk-1234567890',);
        $client = $openAI->getClient($config);
        $this->assertInstanceOf(Client::class, $client);
        /** @var \GuzzleHttp\Client $guzzleClient */
        $guzzleClient = $this->getNonpublicProperty($client, 'client');
        $headers = $guzzleClient->getConfig('headers');
        $this->assertSame($apiKey, $headers['api-key']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testApiKey()
    {
        [, $config] = $this->buildClient();
        $this->assertNotEmpty($config->getApiKey());
    }

    public function testChat()
    {
        [, , $client] = $this->buildClient();
        $response = $client->chat([
            new SystemMessage('You are a Robot created by Hyperf, your purpose is to make people happy.'),
            new UserMessage('Who are you ?')
        ], 'gpt-35-turbo', temperature: 0.4);
        $this->assertTrue($response->isSuccess());
        $this->assertCount(1, $response->getChoices());
        $this->assertTrue(str_contains($response->getChoices()[0]->getMessage()->getContent(), 'Hyperf'));
        // Assert Usage
        $usage = $response->getUsage();
        $this->assertGreaterThan(0, $usage->getCompletionTokens());
        $this->assertGreaterThan(0, $usage->getPromptTokens());
        $this->assertGreaterThan(0, $usage->getTotalTokens());
    }

    /**
     * @skip
     */
    public function testCompletions()
    {
        $this->markTestSkipped('Azure OpenAI still does not created a model that support completions');
        [, , $client] = $this->buildClient();
        $response = $client->completions('1+1=?', 'text-davinci-003', temperature: 0.4);
        $this->assertTrue($response->isSuccess());
        $this->assertCount(1, $response->getChoices());
        $this->assertTrue(str_contains($response->getChoices()[0]->getText(), '2'));
        // Assert Usage
        $usage = $response->getUsage();
        $this->assertGreaterThan(0, $usage->getCompletionTokens());
        $this->assertGreaterThan(0, $usage->getPromptTokens());
        $this->assertGreaterThan(0, $usage->getTotalTokens());
    }

    public function testModels()
    {
        $this->expectException(NotImplementedException::class);
        [, , $client] = $this->buildClient();
        $client->models();
    }

    /**
     * @return array{0: AzureOpenAI, 1: Client, 2: AzureOpenAIConfig}
     */
    protected function buildClient(): array
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY'), baseUrl: ('AZURE_OPENAI_API_BASE'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
        $client = $openAI->getClient($config);
        return [$openAI, $config, $client];
    }

}
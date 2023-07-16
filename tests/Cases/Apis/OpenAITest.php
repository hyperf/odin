<?php

namespace HyperfTest\Odin\Cases\Apis;

use Hyperf\Odin\Apis\OpenAI\Client;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\Response\Model;
use Hyperf\Odin\Apis\SystemMessage;
use Hyperf\Odin\Apis\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

class OpenAITest extends AbstractTestCase
{

    public function testGetClient()
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig($apiKey = 'sk-1234567890', $organization = 'org-1234567890');
        $client = $openAI->getClient($config);
        $this->assertInstanceOf(Client::class, $client);
        /** @var \GuzzleHttp\Client $guzzleClient */
        $guzzleClient = $this->getNonpublicProperty($client, 'client');
        $headers = $guzzleClient->getConfig('headers');
        $this->assertSame('Bearer ' . $apiKey, $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame($organization, $headers['OpenAI-Organization']);
    }

    public function testApiKey()
    {
        [, $config] = $this->buildClient();
        $this->assertTrue(str_starts_with($config->getApiKey(), 'sk-'));
    }

    public function testChat()
    {
        [, , $client] = $this->buildClient();
        $response = $client->chat([
            new SystemMessage('You are a Robot created by Hyperf, your purpose is to make people happy.'),
            new UserMessage('Who are you ?')
        ], 'gpt-3.5-turbo', temperature: 0.4);
        $this->assertTrue($response->isSuccess());
        $this->assertCount(1, $response->getChoices());
        $this->assertTrue(str_contains($response->getChoices()[0]->getMessage()->getContent(), 'Hyperf'));
        // Assert Usage
        $usage = $response->getUsage();
        $this->assertGreaterThan(0, $usage->getCompletionTokens());
        $this->assertGreaterThan(0, $usage->getPromptTokens());
        $this->assertGreaterThan(0, $usage->getTotalTokens());
    }

    public function testCompletions()
    {
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
        [, , $client] = $this->buildClient();
        $response = $client->models();
        $this->assertTrue($response->isSuccess());
        $this->assertGreaterThan(0, count($response->getData()));
        $this->assertInstanceOf(Model::class, current($response->getData()));
        $this->assertSame('whisper-1', $response->getData()[0]->getId());
    }

    /**
     * @return array{0: OpenAI, 1: OpenAIConfig, 2: Client}
     */
    protected function buildClient(): array
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig(\Hyperf\Support\env('OPENAI_API_KEY_FOR_TEST'),);
        $client = $openAI->getClient($config);
        return [$openAI, $config, $client];
    }

}
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

namespace HyperfTest\Odin\Cases\Api\Providers\AwsBedrock;

use Hyperf\Odin\Api\Providers\AwsBedrock\ConverseConverter;
use Hyperf\Odin\Api\Providers\AwsBedrock\MergedToolMessage;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\CachePoint;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\ConverseConverter
 */
class ConverseConverterTest extends AbstractTestCase
{
    private ConverseConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = new ConverseConverter();
    }

    public function testConvertSystemMessage()
    {
        $systemMessage = new SystemMessage('You are a helpful assistant.');
        $result = $this->converter->convertSystemMessage($systemMessage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('text', $result[0]);
        $this->assertEquals('You are a helpful assistant.', $result[0]['text']);
    }

    public function testConvertSystemMessageWithCachePoint()
    {
        $systemMessage = new SystemMessage('You are a helpful assistant.');
        $systemMessage->setCachePoint(new CachePoint('default'));

        $result = $this->converter->convertSystemMessage($systemMessage);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('text', $result[0]);
        $this->assertArrayHasKey('cachePoint', $result[1]);
        $this->assertEquals('default', $result[1]['cachePoint']['type']);
    }

    public function testConvertUserMessage()
    {
        $userMessage = new UserMessage('Hello world');
        $result = $this->converter->convertUserMessage($userMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertArrayHasKey('text', $result['content'][0]);
        $this->assertEquals('Hello world', $result['content'][0]['text']);
    }

    public function testConvertAssistantMessage()
    {
        $assistantMessage = new AssistantMessage('Hello there!');
        $result = $this->converter->convertAssistantMessage($assistantMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::Assistant->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertArrayHasKey('text', $result['content'][0]);
        $this->assertEquals('Hello there!', $result['content'][0]['text']);
    }

    public function testConvertSingleToolMessageWithoutCachePoint()
    {
        $toolMessage = new ToolMessage('Weather result', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $result = $this->converter->convertToolMessage($toolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(1, $result['content']);

        // Check tool result structure
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertArrayHasKey('toolUseId', $result['content'][0]['toolResult']);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);
    }

    public function testConvertSingleToolMessageWithCachePoint()
    {
        $toolMessage = new ToolMessage('Weather result', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage->setCachePoint(new CachePoint('default'));

        $result = $this->converter->convertToolMessage($toolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(2, $result['content']);

        // Check tool result structure
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);

        // Check cache point
        $this->assertArrayHasKey('cachePoint', $result['content'][1]);
        $this->assertEquals('default', $result['content'][1]['cachePoint']['type']);
    }

    public function testConvertMergedToolMessageWithoutCachePoint()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);

        $mergedToolMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        $result = $this->converter->convertToolMessage($mergedToolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(2, $result['content']); // Only 2 tool results, no cache point

        // Check first tool result
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);

        // Check second tool result
        $this->assertArrayHasKey('toolResult', $result['content'][1]);
        $this->assertEquals('tool_call_2', $result['content'][1]['toolResult']['toolUseId']);
    }

    public function testConvertMergedToolMessageWithAllCachePoints()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage1->setCachePoint(new CachePoint('default'));

        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        $toolMessage2->setCachePoint(new CachePoint('default'));

        $mergedToolMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        $result = $this->converter->convertToolMessage($mergedToolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(3, $result['content']); // 2 tool results + 1 cache point

        // Check first tool result
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);

        // Check second tool result
        $this->assertArrayHasKey('toolResult', $result['content'][1]);
        $this->assertEquals('tool_call_2', $result['content'][1]['toolResult']['toolUseId']);

        // Check cache point
        $this->assertArrayHasKey('cachePoint', $result['content'][2]);
        $this->assertEquals('default', $result['content'][2]['cachePoint']['type']);
    }

    public function testConvertMergedToolMessageWithPartialCachePoints()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage1->setCachePoint(new CachePoint('default')); // Has cache point

        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        // No cache point

        $mergedToolMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        $result = $this->converter->convertToolMessage($mergedToolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(3, $result['content']); // 2 tool results + 1 cache point

        // Check first tool result
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);

        // Check second tool result
        $this->assertArrayHasKey('toolResult', $result['content'][1]);
        $this->assertEquals('tool_call_2', $result['content'][1]['toolResult']['toolUseId']);

        // Check cache point (should be present because at least one tool message has it)
        $this->assertArrayHasKey('cachePoint', $result['content'][2]);
        $this->assertEquals('default', $result['content'][2]['cachePoint']['type']);
    }

    public function testConvertMergedToolMessageWithNoCachePoints()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        // Neither has cache point

        $mergedToolMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        $result = $this->converter->convertToolMessage($mergedToolMessage);

        $this->assertIsArray($result);
        $this->assertEquals(Role::User->value, $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertIsArray($result['content']);
        $this->assertCount(2, $result['content']); // Only 2 tool results, no cache point

        // Check first tool result
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertEquals('tool_call_1', $result['content'][0]['toolResult']['toolUseId']);

        // Check second tool result
        $this->assertArrayHasKey('toolResult', $result['content'][1]);
        $this->assertEquals('tool_call_2', $result['content'][1]['toolResult']['toolUseId']);
    }

    public function testConvertToolMessageWithJsonContent()
    {
        $jsonContent = json_encode(['temperature' => 25, 'condition' => 'sunny']);
        $toolMessage = new ToolMessage($jsonContent, 'tool_call_1', 'weather', ['city' => 'Beijing']);

        $result = $this->converter->convertToolMessage($toolMessage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertArrayHasKey('content', $result['content'][0]['toolResult']);
        $this->assertArrayHasKey('json', $result['content'][0]['toolResult']['content'][0]);

        $expectedJson = ['temperature' => 25, 'condition' => 'sunny'];
        $this->assertEquals($expectedJson, $result['content'][0]['toolResult']['content'][0]['json']);
    }

    public function testConvertToolMessageWithNonJsonContent()
    {
        $toolMessage = new ToolMessage('Simple text result', 'tool_call_1', 'weather', ['city' => 'Beijing']);

        $result = $this->converter->convertToolMessage($toolMessage);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('toolResult', $result['content'][0]);
        $this->assertArrayHasKey('content', $result['content'][0]['toolResult']);
        $this->assertArrayHasKey('json', $result['content'][0]['toolResult']['content'][0]);

        $expectedJson = ['result' => 'Simple text result'];
        $this->assertEquals($expectedJson, $result['content'][0]['toolResult']['content'][0]['json']);
    }

    public function testConvertTools()
    {
        $toolDefinition = new ToolDefinition(
            name: 'weather',
            description: 'Get weather information',
            parameters: ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name',
                    ],
                ],
                'required' => ['city'],
            ]),
            toolHandler: function (array $params) {
                return ['result' => 'weather data for ' . $params['city']];
            }
        );

        $result = $this->converter->convertTools([$toolDefinition]);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('toolSpec', $result[0]);
        $this->assertArrayHasKey('name', $result[0]['toolSpec']);
        $this->assertEquals('weather', $result[0]['toolSpec']['name']);
        $this->assertArrayHasKey('description', $result[0]['toolSpec']);
        $this->assertEquals('Get weather information', $result[0]['toolSpec']['description']);
        $this->assertArrayHasKey('inputSchema', $result[0]['toolSpec']);
        $this->assertArrayHasKey('json', $result[0]['toolSpec']['inputSchema']);
    }

    public function testConvertToolsWithCache()
    {
        $toolDefinition = new ToolDefinition(
            name: 'weather',
            description: 'Get weather information',
            parameters: ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name',
                    ],
                ],
                'required' => ['city'],
            ]),
            toolHandler: function (array $params) {
                return ['result' => 'weather data for ' . $params['city']];
            }
        );

        $result = $this->converter->convertTools([$toolDefinition], true);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // 1 tool + 1 cache point
        $this->assertArrayHasKey('toolSpec', $result[0]);
        $this->assertArrayHasKey('cachePoint', $result[1]);
        $this->assertEquals('default', $result[1]['cachePoint']['type']);
    }
}

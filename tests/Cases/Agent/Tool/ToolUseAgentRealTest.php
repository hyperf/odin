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

namespace HyperfTest\Odin\Cases\Agent\Tool;

use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\AbstractTestCase;

use function Hyperf\Support\env;

/**
 * 测试 ToolUseAgent 类的功能.
 * @internal
 * @coversNothing
 */
class ToolUseAgentRealTest extends AbstractTestCase
{
    /**
     * 测试 chat 方法能正确返回聊天结果.
     */
    public function testChat()
    {
        $this->markTestSkipped('这里是真实的调用');

        $logger = new Logger();

        $model = ModelFactory::create(
            implementation: AzureOpenAIModel::class,
            modelName: 'gpt-4o-global',
            config: [
                'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
                'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
                'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
                'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
            ],
            modelOptions: ModelOptions::fromArray([
                'chat' => true,
                'function_call' => true,
                'embedding' => false,
                'multi_modal' => true,
                'vector_size' => 0,
            ]),
            apiOptions: ApiOptions::fromArray([
                'timeout' => [
                    'connection' => 5.0,  // 连接超时（秒）
                    'write' => 10.0,      // 写入超时（秒）
                    'read' => 300.0,       // 读取超时（秒）
                    'total' => 350.0,     // 总体超时（秒）
                    'thinking' => 120.0,  // 思考超时（秒）
                    'stream_chunk' => 30.0, // 流式块间超时（秒）
                    'stream_first' => 60.0, // 首个流式块超时（秒）
                ],
                'custom_error_mapping_rules' => [],
            ]),
            logger: $logger
        );
        $memory = new MemoryManager();
        $memory->addSystemMessage(new SystemMessage(''));

        $tool = new ToolDefinition(
            name: 'calculator',
            description: '计算器工具',
            parameters: ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'a' => [
                        'type' => 'number',
                        'description' => '需要相加的数字',
                    ],
                    'b' => [
                        'type' => 'number',
                        'description' => '需要相加的数字',
                    ],
                ],
                'required' => ['a', 'b'],
            ]),
            toolHandler: function ($params) {
                return ['result' => $params['a'] + $params['b']];
            }
        );

        $agent = new ToolUseAgent(
            model: $model,
            memory: $memory,
            tools: [$tool->getName() => $tool],
            temperature: 0.6,
            logger: $logger
        );

        $userMessage = new UserMessage('请使用计算器计算 7 + 8。涉及到调用工具时，请在调用工具的同时详细说明作用和步骤。');
        $response = $agent->chat($userMessage);

        $this->assertStringContainsString('15', $response->getFirstChoice()->getMessage()->getContent());
    }

    /**
     * 测试 chatStreamed 方法能正确流式返回聊天结果.
     */
    public function testChatStreamed()
    {
        $this->markTestSkipped('这里是真实的调用');

        $logger = new Logger();

        $model = ModelFactory::create(
            implementation: AzureOpenAIModel::class,
            modelName: 'gpt-4o-global',
            config: [
                'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
                'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
                'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
                'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
            ],
            modelOptions: ModelOptions::fromArray([
                'chat' => true,
                'function_call' => true,
                'embedding' => false,
                'multi_modal' => true,
                'vector_size' => 0,
            ]),
            apiOptions: ApiOptions::fromArray([
                'timeout' => [
                    'connection' => 5.0,  // 连接超时（秒）
                    'write' => 10.0,      // 写入超时（秒）
                    'read' => 300.0,       // 读取超时（秒）
                    'total' => 350.0,     // 总体超时（秒）
                    'thinking' => 120.0,  // 思考超时（秒）
                    'stream_chunk' => 30.0, // 流式块间超时（秒）
                    'stream_first' => 60.0, // 首个流式块超时（秒）
                ],
                'custom_error_mapping_rules' => [],
            ]),
            logger: $logger
        );
        $memory = new MemoryManager();
        $memory->addSystemMessage(new SystemMessage(''));

        $tool = new ToolDefinition(
            name: 'calculator',
            description: '计算器工具',
            parameters: ToolParameters::fromArray([
                'type' => 'object',
                'properties' => [
                    'a' => [
                        'type' => 'number',
                        'description' => '需要相加的数字',
                    ],
                    'b' => [
                        'type' => 'number',
                        'description' => '需要相加的数字',
                    ],
                ],
                'required' => ['a', 'b'],
            ]),
            toolHandler: function ($params) {
                return ['result' => $params['a'] + $params['b']];
            }
        );

        $agent = new ToolUseAgent(
            model: $model,
            memory: $memory,
            tools: [$tool->getName() => $tool],
            temperature: 0.6,
            logger: $logger
        );

        $userMessage = new UserMessage('请使用计算器计算 7 + 8。涉及到调用工具时，请在调用工具的同时详细说明作用和步骤。');
        $response = $agent->chatStreamed($userMessage);

        $content = '';
        /** @var ChatCompletionChoice $choice */
        foreach ($response as $choice) {
            $content .= $choice->getMessage()->getContent();
        }

        $this->assertStringContainsString('15', $content);
    }
}

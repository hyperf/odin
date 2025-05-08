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

namespace HyperfTest\Odin\Cases\Utils;

use Hyperf\Odin\Api\Response\ToolCall;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\ToolMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameter;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use Hyperf\Odin\Utils\TokenEstimator;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Utils\TokenEstimator
 */
class TokenEstimatorTest extends TestCase
{
    private TokenEstimator $tokenEstimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenEstimator = new TokenEstimator();
    }

    public function testEstimateTokensWithEmptyText()
    {
        $this->assertEquals(0, $this->tokenEstimator->estimateTokens(''));
    }

    public function testEstimateTokensWithText()
    {
        $text = '你好，世界！Hello, World!';
        $tokens = $this->tokenEstimator->estimateTokens($text);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateTokensWithChineseText()
    {
        $text = '这是一段中文文本，用于测试中文分词。';
        $tokens = $this->tokenEstimator->estimateTokens($text);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateTokensWithEnglishText()
    {
        $text = 'This is an English text for testing token estimation.';
        $tokens = $this->tokenEstimator->estimateTokens($text);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateSystemMessageTokens()
    {
        $message = new SystemMessage('你是一个有用的AI助手');
        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateUserMessageTokens()
    {
        $message = new UserMessage('请帮我解答这个问题');
        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateUserMessageWithContentsTokens()
    {
        $contents = [
            UserMessageContent::text('这是一条带有图片的消息'),
            UserMessageContent::imageUrl('https://example.com/image.jpg'),
        ];
        $message = new UserMessage();
        foreach ($contents as $content) {
            $message->addContent($content);
        }

        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateAssistantMessageTokens()
    {
        $message = new AssistantMessage('这是助手的回复');
        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateAssistantMessageWithToolCallsTokens()
    {
        $toolCall = new ToolCall(
            'search',
            ['query' => '搜索关键词', 'limit' => 5],
            'call_123456',
            'function'
        );

        $message = new AssistantMessage('需要搜索相关信息', [$toolCall]);

        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);

        // 验证带工具调用的消息token数量应大于普通消息
        $simpleMessage = new AssistantMessage('需要搜索相关信息');
        $simpleTokens = $this->tokenEstimator->estimateMessageTokens($simpleMessage);
        $this->assertGreaterThan($simpleTokens, $tokens);
    }

    public function testEstimateToolMessageTokens()
    {
        $message = new ToolMessage('tool-id', '这是工具的输出结果');
        $tokens = $this->tokenEstimator->estimateMessageTokens($message);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateToolsTokensWithEmptyArray()
    {
        $this->assertEquals(0, $this->tokenEstimator->estimateToolsTokens([]));
    }

    public function testEstimateToolsTokensWithToolDefinitions()
    {
        $toolParameter = ToolParameter::string('query', '查询参数', true);
        $toolParameters = new ToolParameters([$toolParameter]);
        $toolHandler = function (array $params) {
            return ['result' => 'test result'];
        };
        $toolDefinition = new ToolDefinition('search', '搜索工具', $toolParameters, $toolHandler);

        $tokens = $this->tokenEstimator->estimateToolsTokens([$toolDefinition]);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function testEstimateToolsTokensWithToolArray()
    {
        $toolArray = [
            'name' => 'calculator',
            'description' => '计算器工具',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'expression' => [
                        'type' => 'string',
                        'description' => '要计算的表达式',
                    ],
                ],
                'required' => ['expression'],
            ],
        ];

        $tokens = $this->tokenEstimator->estimateToolsTokens([$toolArray]);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }
}

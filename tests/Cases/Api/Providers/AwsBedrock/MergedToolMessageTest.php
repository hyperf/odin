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

use Hyperf\Odin\Api\Providers\AwsBedrock\MergedToolMessage;
use Hyperf\Odin\Message\CachePoint;
use Hyperf\Odin\Message\Role;
use Hyperf\Odin\Message\ToolMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\AwsBedrock\MergedToolMessage
 */
class MergedToolMessageTest extends AbstractTestCase
{
    public function testConstructWithMultipleToolMessages()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        // Test that it extends ToolMessage
        $this->assertInstanceOf(ToolMessage::class, $mergedMessage);
        
        // Test that it inherits from first message
        $this->assertEquals('Result 1', $mergedMessage->getContent());
        $this->assertEquals('tool_call_1', $mergedMessage->getToolCallId());
        $this->assertEquals('weather', $mergedMessage->getName());
        $this->assertEquals(['city' => 'Beijing'], $mergedMessage->getArguments());
        
        // Test role
        $this->assertEquals(Role::Tool, $mergedMessage->getRole());
    }

    public function testGetToolMessages()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        $toolMessage3 = new ToolMessage('Result 3', 'tool_call_3', 'weather', ['city' => 'Shenzhen']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2, $toolMessage3]);
        
        $toolMessages = $mergedMessage->getToolMessages();
        
        $this->assertIsArray($toolMessages);
        $this->assertCount(3, $toolMessages);
        $this->assertSame($toolMessage1, $toolMessages[0]);
        $this->assertSame($toolMessage2, $toolMessages[1]);
        $this->assertSame($toolMessage3, $toolMessages[2]);
    }

    public function testIsMerged()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        $this->assertTrue($mergedMessage->isMerged());
    }

    public function testToArray()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        $result = $mergedMessage->toArray();
        
        $this->assertIsArray($result);
        $this->assertEquals(Role::Tool->value, $result['role']);
        $this->assertEquals('Result 1', $result['content']);
        $this->assertEquals('tool_call_1', $result['tool_call_id']);
        $this->assertEquals('weather', $result['name']);
        $this->assertEquals(['city' => 'Beijing'], $result['arguments']);
    }

    public function testInheritanceFromFirstMessage()
    {
        $toolMessage1 = new ToolMessage('First result', 'first_id', 'first_tool', ['param1' => 'value1']);
        $toolMessage2 = new ToolMessage('Second result', 'second_id', 'second_tool', ['param2' => 'value2']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        // Should inherit all properties from first message
        $this->assertEquals('First result', $mergedMessage->getContent());
        $this->assertEquals('first_id', $mergedMessage->getToolCallId());
        $this->assertEquals('first_tool', $mergedMessage->getName());
        $this->assertEquals(['param1' => 'value1'], $mergedMessage->getArguments());
        
        // But should still contain all original messages
        $toolMessages = $mergedMessage->getToolMessages();
        $this->assertCount(2, $toolMessages);
        $this->assertEquals('First result', $toolMessages[0]->getContent());
        $this->assertEquals('Second result', $toolMessages[1]->getContent());
    }

    public function testCachePointHandling()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage1->setCachePoint(new CachePoint('default'));
        
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        // MergedToolMessage should inherit cache point from first message
        $this->assertNotNull($mergedMessage->getCachePoint());
        $this->assertEquals('default', $mergedMessage->getCachePoint()->getType());
        
        // Original messages should retain their cache points
        $toolMessages = $mergedMessage->getToolMessages();
        $this->assertNotNull($toolMessages[0]->getCachePoint());
        $this->assertNull($toolMessages[1]->getCachePoint());
    }

    public function testWithSingleToolMessage()
    {
        $toolMessage = new ToolMessage('Single result', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage]);
        
        $this->assertTrue($mergedMessage->isMerged());
        $this->assertCount(1, $mergedMessage->getToolMessages());
        $this->assertSame($toolMessage, $mergedMessage->getToolMessages()[0]);
        
        // Should still inherit from the single message
        $this->assertEquals('Single result', $mergedMessage->getContent());
        $this->assertEquals('tool_call_1', $mergedMessage->getToolCallId());
    }

    public function testModifyingOriginalMessages()
    {
        $toolMessage1 = new ToolMessage('Result 1', 'tool_call_1', 'weather', ['city' => 'Beijing']);
        $toolMessage2 = new ToolMessage('Result 2', 'tool_call_2', 'weather', ['city' => 'Shanghai']);
        
        $mergedMessage = new MergedToolMessage([$toolMessage1, $toolMessage2]);
        
        // Modify original message
        $toolMessage1->setContent('Modified result');
        
        // The merged message should reflect the change in the original message
        $this->assertEquals('Modified result', $mergedMessage->getToolMessages()[0]->getContent());
        
        // But the merged message's own content should remain unchanged (copied at construction time)
        $this->assertEquals('Result 1', $mergedMessage->getContent());
    }
} 
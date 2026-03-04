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

namespace HyperfTest\Odin\Cases\Api\Transport;

use Hyperf\Odin\Api\Transport\SSEClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test SSEClient handling of large events (> 8KB).
 *
 * This test verifies that the dynamic buffer implementation
 * can handle SSE events larger than the original 8KB limit.
 * @internal
 * @coversNothing
 */
class SSEClientLargeEventTest extends TestCase
{
    /**
     * Test that SSEClient can handle events larger than 8KB.
     */
    public function testHandleLargeEvent(): void
    {
        // Create a large data payload (20KB)
        $largeData = str_repeat('A', 20 * 1024);
        $jsonData = json_encode(['large_field' => $largeData]);

        // Create SSE formatted stream
        $sseContent = "data: {$jsonData}\n\n";

        // Create stream resource
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $sseContent);
        rewind($stream);

        // Create SSEClient
        $client = new SSEClient($stream, false);

        // Iterate and collect events
        $events = [];
        foreach ($client->getIterator() as $event) {
            $events[] = $event;
        }

        // Assertions
        $this->assertCount(1, $events);
        $this->assertIsArray($events[0]->getData());
        $this->assertArrayHasKey('large_field', $events[0]->getData());
        $this->assertEquals($largeData, $events[0]->getData()['large_field']);

        fclose($stream);
    }

    /**
     * Test that SSEClient can handle multiple large events.
     */
    public function testHandleMultipleLargeEvents(): void
    {
        $events = [];

        // Create 3 large events (each 15KB)
        for ($i = 0; $i < 3; ++$i) {
            $largeData = str_repeat("Event{$i}", 15 * 1024 / 6);
            $jsonData = json_encode(['event_id' => $i, 'data' => $largeData]);
            $events[] = "data: {$jsonData}\n\n";
        }

        $sseContent = implode('', $events);

        // Create stream resource
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $sseContent);
        rewind($stream);

        // Create SSEClient
        $client = new SSEClient($stream, false);

        // Iterate and collect events
        $receivedEvents = [];
        foreach ($client->getIterator() as $event) {
            $receivedEvents[] = $event;
        }

        // Assertions
        $this->assertCount(3, $receivedEvents);

        for ($i = 0; $i < 3; ++$i) {
            $this->assertIsArray($receivedEvents[$i]->getData());
            $this->assertEquals($i, $receivedEvents[$i]->getData()['event_id']);
        }

        fclose($stream);
    }

    /**
     * Test that SSEClient rejects events larger than 1MB.
     */
    public function testRejectExcessivelyLargeEvent(): void
    {
        // Create an event larger than 1MB
        $excessiveData = str_repeat('X', 2 * 1024 * 1024); // 2MB
        $jsonData = json_encode(['excessive_field' => $excessiveData]);

        // Create incomplete SSE event (missing \n\n to trigger buffer accumulation)
        $sseContent = "data: {$jsonData}";

        // Create stream resource
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $sseContent);
        rewind($stream);

        // Create SSEClient
        $client = new SSEClient($stream, false);

        // Expect RuntimeException for excessive size
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSE event exceeds maximum size');

        // Try to iterate (should throw exception)
        foreach ($client->getIterator() as $event) {
            // Should not reach here
        }

        fclose($stream);
    }

    /**
     * Test that SSEClient handles partial events correctly.
     */
    public function testHandlePartialEvent(): void
    {
        // Create a large event split across multiple reads
        $largeData = str_repeat('B', 10 * 1024);
        $jsonData = json_encode(['partial_field' => $largeData]);
        $sseContent = "data: {$jsonData}\n\n";

        // Create stream resource
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $sseContent);
        rewind($stream);

        // Create SSEClient
        $client = new SSEClient($stream, false);

        // Iterate and collect events
        $events = [];
        foreach ($client->getIterator() as $event) {
            $events[] = $event;
        }

        // Assertions
        $this->assertCount(1, $events);
        $this->assertIsArray($events[0]->getData());
        $this->assertEquals($largeData, $events[0]->getData()['partial_field']);

        fclose($stream);
    }

    /**
     * Test backward compatibility with small events.
     */
    public function testBackwardCompatibilityWithSmallEvents(): void
    {
        $sseContent = "data: {\"message\":\"Hello\"}\n\n"
                      . "data: {\"message\":\"World\"}\n\n";

        // Create stream resource
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $sseContent);
        rewind($stream);

        // Create SSEClient
        $client = new SSEClient($stream, false);

        // Iterate and collect events
        $events = [];
        foreach ($client->getIterator() as $event) {
            $events[] = $event;
        }

        // Assertions
        $this->assertCount(2, $events);
        $this->assertEquals('Hello', $events[0]->getData()['message']);
        $this->assertEquals('World', $events[1]->getData()['message']);

        fclose($stream);
    }
}

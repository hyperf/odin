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

namespace Hyperf\Odin\Api\Transport;

use Generator;
use Hyperf\Odin\Exception\InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use Psr\Log\LoggerInterface;

class SSEClient implements IteratorAggregate
{
    private const EOL = "\n";

    private const EVENT_END = "\n\n";

    private const BUFFER_SIZE = 8192;

    private const DEFAULT_RETRY = 3000;

    private int $retryTimeout = self::DEFAULT_RETRY;

    private ?string $lastEventId = null;

    private ?StreamExceptionDetector $exceptionDetector = null;

    private bool $shouldClose = false;

    public function __construct(
        private $stream,
        private bool $autoClose = true,
        ?array $timeoutConfig = null,
        ?LoggerInterface $logger = null
    ) {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('Stream must be a resource');
        }

        if ($timeoutConfig !== null) {
            $this->exceptionDetector = new StreamExceptionDetector($timeoutConfig, $logger);
        }
    }

    public function __destruct()
    {
        if ($this->autoClose && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function getIterator(): Generator
    {
        try {
            $lastCheckTime = microtime(true);
            $chunkCounter = 0;

            while (! feof($this->stream) && ! $this->shouldClose) {
                $now = microtime(true);
                if ($now - $lastCheckTime > 1.0) {
                    $lastCheckTime = $now;
                    $this->exceptionDetector?->checkTimeout();
                }

                $chunk = stream_get_line($this->stream, self::BUFFER_SIZE, self::EVENT_END);

                if ($chunk === false) {
                    $this->exceptionDetector?->checkTimeout();
                    continue;
                }

                ++$chunkCounter;

                $eventData = $this->parseEvent($chunk);
                $event = SSEEvent::fromArray($eventData);

                if ($event->getId() !== null) {
                    $this->lastEventId = $event->getId();
                }

                if ($event->getRetry() !== null) {
                    $retryInt = (int) $event->getRetry();
                    if ($retryInt > 0 && $retryInt <= 600000) {
                        $this->retryTimeout = $retryInt;
                    }
                }

                if ($event->isEmpty()) {
                    continue;
                }

                $chunkInfo = [
                    'event_type' => $event->getEvent(),
                    'event_id' => $event->getId(),
                    'data_preview' => is_string($event->getData())
                        ? substr($event->getData(), 0, 200)
                        : (is_array($event->getData()) ? json_encode($event->getData()) : 'non-string-data'),
                    'raw_chunk_size' => strlen($chunk),
                ];
                $this->exceptionDetector?->onChunkReceived($chunkInfo);

                yield $event;

                if (! is_resource($this->stream) || feof($this->stream)) {
                    break;
                }
            }
        } finally {
            if ($this->autoClose && is_resource($this->stream)) {
                fclose($this->stream);
            }
        }
    }

    public function getLastEventId(): ?string
    {
        return $this->lastEventId;
    }

    public function getRetryTimeout(): int
    {
        return $this->retryTimeout;
    }

    public function closeEarly(): void
    {
        $this->shouldClose = true;
    }

    protected function parseEvent(string $chunk): array
    {
        $result = [
            'event' => 'message',
            'data' => '',
            'id' => null,
            'retry' => null,
        ];

        $chunk = preg_replace('/^\xEF\xBB\xBF/', '', $chunk);
        $lines = preg_split('/' . self::EOL . '/', $chunk);

        foreach ($lines as $line) {
            if (empty($line) || str_starts_with($line, ':')) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$field, $value] = explode(':', $line, 2);
                $value = ltrim($value, ' ');

                switch ($field) {
                    case 'event':
                        $result['event'] = $value;
                        break;
                    case 'data':
                        $result['data'] = $result['data'] ? $result['data'] . "\n" . $value : $value;
                        break;
                    case 'id':
                        $result['id'] = $value;
                        break;
                    case 'retry':
                        if (is_numeric($value)) {
                            $retry = (int) $value;
                            if ($retry > 0) {
                                $result['retry'] = $retry;
                            }
                        }
                        break;
                }
            } else {
                if ($line === 'data') {
                    $result['data'] = $result['data'] ? $result['data'] . "\n" : '';
                }
            }
        }

        if (! empty($result['data'])) {
            if ($result['data'] === '[DONE]') {
                $result['event'] = 'done';
            } else {
                try {
                    $jsonData = json_decode($result['data'], true, 512, JSON_THROW_ON_ERROR);
                    $result['data'] = $jsonData;
                } catch (JsonException $e) {
                }
            }
        }

        return $result;
    }
}

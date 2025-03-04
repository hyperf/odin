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

namespace Hyperf\Odin\Api\Response;

use Generator;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Api\Transport\SSEClient;
use Hyperf\Odin\Api\Transport\SSEEvent;
use Hyperf\Odin\Exception\LLMException;
use IteratorAggregate;
use JsonException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

class ChatCompletionStreamResponse extends AbstractResponse implements Stringable
{
    protected ?string $id = null;

    protected ?string $object = null;

    protected ?int $created = null;

    protected ?string $model = null;

    protected array $choices = [];

    /**
     * 兼容多种类型的迭代器.
     */
    protected ?SSEClient $sseClient = null;

    /**
     * 支持 IteratorAggregate 接口的迭代器.
     */
    protected ?IteratorAggregate $iterator = null;

    /**
     * 构造函数.
     *
     * @param null|PsrResponseInterface $response HTTP 响应对象
     * @param null|LoggerInterface $logger 日志记录器
     * @param null|IteratorAggregate|SSEClient $streamIterator 流式迭代器，可以是 SSEClient 或 IteratorAggregate
     */
    public function __construct(?PsrResponseInterface $response = null, ?LoggerInterface $logger = null, $streamIterator = null)
    {
        // 根据类型初始化不同的迭代器
        if ($streamIterator instanceof SSEClient) {
            $this->sseClient = $streamIterator;
        } elseif ($streamIterator instanceof IteratorAggregate) {
            $this->iterator = $streamIterator;
        }

        if ($response === null) {
            if (! $this->sseClient && ! $this->iterator) {
                throw new LLMException('Stream iterator is required');
            }
            $response = new Response(200);
        }

        parent::__construct($response, $logger);
    }

    public function __toString(): string
    {
        return 'Stream Response';
    }

    public function getStreamIterator(): Generator
    {
        // 优先使用 IteratorAggregate
        if ($this->iterator) {
            return $this->iterateWithCustomIterator();
        }

        // 其次使用 SSEClient
        if ($this->sseClient) {
            return $this->iterateWithSSEClient();
        }

        // 最后使用传统方式
        return $this->iterateWithLegacyMethod();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(?string $object): self
    {
        $this->object = $object;
        return $this;
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(null|int|string $created): self
    {
        $this->created = (int) $created;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    public function setChoices(array $choices): self
    {
        $this->choices = $choices;
        return $this;
    }

    protected function parseContent(): self
    {
        return $this;
    }

    /**
     * 使用自定义迭代器（IteratorAggregate）处理流数据.
     */
    private function iterateWithCustomIterator(): Generator
    {
        try {
            foreach ($this->iterator->getIterator() as $data) {
                // 处理结束标记
                if ($data === '[DONE]' || $data === json_encode('[DONE]')) {
                    $this->logger?->debug('Stream completed');
                    break;
                }

                // 解析 JSON 数据（如果数据是字符串）
                if (is_string($data)) {
                    try {
                        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        $this->logger?->warning('Invalid JSON in stream', ['data' => $data, 'error' => $e->getMessage()]);
                        continue;
                    }
                }

                // 确保数据是有效的数组
                if (! is_array($data)) {
                    $this->logger?->warning('Invalid data format', ['data' => $data]);
                    continue;
                }

                // 更新响应元数据
                $this->updateMetadata($data);

                // 生成ChatCompletionChoice对象
                yield from $this->yieldChoices($data['choices'] ?? []);
            }
        } catch (Throwable $e) {
            $this->logger?->error('Error processing custom iterator', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // 重新抛出异常，让调用方可以处理
        }
    }

    /**
     * 使用SSEClient处理流数据.
     */
    private function iterateWithSSEClient(): Generator
    {
        try {
            /** @var SSEEvent $event */
            foreach ($this->sseClient->getIterator() as $event) {
                $data = $event->getData();

                // 处理结束标记
                if ($data === '[DONE]') {
                    $this->logger?->debug('SSE stream completed');
                    break;
                }

                // 只处理数据事件
                if ($event->getEvent() !== 'message') {
                    $this->logger?->debug('Skipping non-message event', ['event' => $event->getEvent()]);
                    continue;
                }

                // 确保数据是有效的数组
                if (! is_array($data)) {
                    $this->logger?->warning('Invalid data format', ['data' => $data]);
                    continue;
                }

                // 更新响应元数据
                $this->updateMetadata($data);

                // 生成ChatCompletionChoice对象
                yield from $this->yieldChoices($data['choices'] ?? []);
            }
        } catch (Throwable $e) {
            $this->logger?->error('Error processing SSE stream', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // 重新抛出异常，让调用方可以处理
        }
    }

    /**
     * 更新响应元数据.
     */
    private function updateMetadata(array $data): void
    {
        $this->setId($data['id'] ?? null);
        $this->setObject($data['object'] ?? null);
        $this->setCreated($data['created'] ?? null);
        $this->setModel($data['model'] ?? null);
        $this->setChoices($data['choices'] ?? []);
    }

    /**
     * 生成选择对象
     */
    private function yieldChoices(array $choices): Generator
    {
        foreach ($choices as $choice) {
            if (! is_array($choice)) {
                $this->logger?->warning('Invalid choice format', ['choice' => $choice]);
                continue;
            }
            yield ChatCompletionChoice::fromArray($choice);
        }
    }

    /**
     * 使用传统方式处理流数据（后备方法）.
     */
    private function iterateWithLegacyMethod(): Generator
    {
        // 保留原有的实现作为后备
        $body = $this->originResponse->getBody();

        $buffer = '';
        while (! $body->eof()) {
            $chunk = $body->read(4096);
            if (! $chunk) {
                break;
            }

            $buffer .= $chunk;
            // 处理接收到的数据块
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // 保留不完整的最后一行

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $line = substr($line, 5);
                }

                if (trim($line) === '[DONE]') {
                    break 2;
                }

                try {
                    $data = json_decode(trim($line), true, 512, JSON_THROW_ON_ERROR);
                    $this->updateMetadata($data);
                    yield from $this->yieldChoices($data['choices'] ?? []);
                } catch (JsonException $e) {
                    $this->logger?->warning('InvalidJsonResponse', ['line' => $line, 'error' => $e->getMessage()]);
                    continue;
                }
            }
        }
    }
}

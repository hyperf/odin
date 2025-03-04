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

namespace Hyperf\Odin\Agent\Tool;

class UsedTool
{
    public function __construct(
        private float $elapsedTime,
        private bool $success,
        private string $id,
        private string $name,
        private array $arguments,
        private mixed $result,
        private string $errorMessage = ''
    ) {}

    public function toArray(): array
    {
        return [
            'elapsed_time' => $this->elapsedTime,
            'success' => $this->success,
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'result' => $this->result,
            'error_message' => $this->errorMessage,
        ];
    }

    public function getElapsedTime(): float
    {
        return $this->elapsedTime;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}

<?php

namespace Hyperf\Odin;


use Psr\Log\LoggerInterface;
use Stringable;

class Logger implements LoggerInterface
{

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $message = (string) $message;
        $message = sprintf('[%s] %s', $level, $message);
        if ($context) {
            $message .= sprintf(' %s', json_encode($context, JSON_UNESCAPED_UNICODE));
        }
        echo $message . PHP_EOL;
    }
}
<?php

namespace Hyperf\Odin;


use Psr\Log\LoggerInterface;
use Stringable;

class Logger implements LoggerInterface
{

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $message = (string)$message;
        $message = sprintf('[%s] %s', $level, $message);
        if ($context) {
            $message .= sprintf(' %s', json_encode($context, JSON_UNESCAPED_UNICODE));
        }
        echo $message . PHP_EOL;
    }
}
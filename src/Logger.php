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

namespace Hyperf\Odin;

use Psr\Log\LoggerInterface;
use Stringable;

class Logger implements LoggerInterface
{
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = (string)$message;
        $datetime = date('Y-m-d H:i:s');
        $message = sprintf('[%s] %s %s', $level, $datetime, $message);
        if ($context) {
            $message .= sprintf(' %s', json_encode($context, JSON_UNESCAPED_UNICODE));
        }
        echo $message . PHP_EOL;
    }
}

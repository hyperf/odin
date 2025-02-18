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

namespace Hyperf\Odin\Utils;

use RuntimeException;
use function Hyperf\Config\config;

class StreamUtil
{
    public static function enabledContext(bool $stream): bool
    {
        return $stream && config('odin.stream.enable_context', false);
    }

    public static function createContext(string $method, string $url, array $options)
    {
        $header = '';
        foreach ($options['headers'] as $key => $value) {
            $header .= $key . ': ' . $value . "\r\n";
        }
        if (isset($options['query'])) {
            $url .= '?' . http_build_query($options['query']);
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $header,
                'content' => json_encode($options['json'] ?? []),
            ],
        ]);
        $stream = fopen($url, 'r', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Failed to open stream: ' . $url);
        }
        return $stream;
    }
}

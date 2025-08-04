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

namespace Hyperf\Odin\Api\Providers;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\Proxy;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Utils;

/**
 * Global HTTP Handler Factory for all API clients
 * Provides centralized HTTP transport configuration for both Guzzle and AWS SDK clients.
 */
class HttpHandlerFactory
{
    /**
     * Create HTTP handler based on specified type.
     *
     * @param string $type Handler type: 'curl', 'stream', 'auto'
     * @return callable HTTP handler
     */
    public static function create(string $type = 'auto'): callable
    {
        return match (strtolower($type)) {
            'stream' => self::createStreamHandler(),
            'auto' => self::createAutoHandler(),
            default => self::createCurlHandler(), // 使用 curl 作为错误类型的后备
        };
    }

    /**
     * Create a Guzzle client with the specified HTTP handler.
     *
     * @param array $options Guzzle client options
     * @param string $handlerType HTTP handler type ('curl', 'stream', 'auto')
     */
    public static function createGuzzleClient(array $options = [], string $handlerType = 'auto'): GuzzleClient
    {
        $handler = self::create($handlerType);
        $stack = HandlerStack::create($handler);

        $options['handler'] = $stack;

        return new GuzzleClient($options);
    }

    /**
     * Create a HandlerStack with middleware support.
     *
     * @param string $type Handler type ('curl', 'stream', 'auto')
     */
    public static function createHandlerStack(string $type = 'auto'): HandlerStack
    {
        $handler = self::create($type);
        return HandlerStack::create($handler);
    }

    /**
     * Create a pure PHP Stream handler (no cURL dependencies).
     */
    public static function createStreamHandler(): callable
    {
        return new StreamHandler();
    }

    /**
     * Create a cURL-based handler.
     */
    public static function createCurlHandler(): callable
    {
        // Check if cURL functions are available
        if (function_exists('curl_multi_exec') && function_exists('curl_exec')) {
            return Proxy::wrapSync(new CurlMultiHandler(), new CurlHandler());
        }
        if (function_exists('curl_exec')) {
            return new CurlHandler();
        }
        if (function_exists('curl_multi_exec')) {
            return new CurlMultiHandler();
        }

        // Fallback to stream handler if cURL is not available
        return self::createStreamHandler();
    }

    /**
     * Create auto-selecting handler (default Guzzle behavior).
     */
    public static function createAutoHandler(): callable
    {
        return Utils::chooseHandler();
    }

    /**
     * Check if a specific handler type is available.
     *
     * @param string $type Handler type to check
     * @return bool True if handler type is available
     */
    public static function isHandlerAvailable(string $type): bool
    {
        return match (strtolower($type)) {
            'stream' => ini_get('allow_url_fopen') !== false,
            'curl' => function_exists('curl_exec') || function_exists('curl_multi_exec'),
            'auto' => true,
            default => false,
        };
    }

    /**
     * Get information about the current PHP environment's HTTP capabilities.
     *
     * @return array Information about available handlers
     */
    public static function getEnvironmentInfo(): array
    {
        return [
            'curl_available' => function_exists('curl_exec'),
            'curl_multi_available' => function_exists('curl_multi_exec'),
            'curl_version' => function_exists('curl_version') ? curl_version() : null,
            'stream_available' => ini_get('allow_url_fopen') !== false,
            'openssl_available' => extension_loaded('openssl'),
            'recommended_handler' => self::getRecommendedHandler(),
        ];
    }

    /**
     * Get the recommended handler for the current environment.
     *
     * @return string Recommended handler type
     */
    public static function getRecommendedHandler(): string
    {
        if (function_exists('curl_multi_exec') && function_exists('curl_exec')) {
            return 'curl'; // Best performance for concurrent requests
        }

        if (ini_get('allow_url_fopen')) {
            return 'stream'; // Pure PHP, no external dependencies
        }

        return 'auto'; // Let Guzzle decide
    }

    /**
     * Create HTTP client options with proper handler configuration.
     *
     * @param array $baseOptions Base options for the client
     * @param string $handlerType Handler type ('curl', 'stream', 'auto')
     * @return array Complete HTTP client options
     */
    public static function createHttpOptions(array $baseOptions = [], string $handlerType = 'auto'): array
    {
        $options = $baseOptions;

        // Only set handler if not using 'auto'
        if ($handlerType !== 'auto') {
            $options['handler'] = self::createHandlerStack($handlerType);
        }

        return $options;
    }
}

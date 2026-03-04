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

use CurlHandle;

/**
 * Proxy configuration utility.
 * Provides methods to parse and configure proxy settings for HTTP clients and cURL.
 */
class ProxyUtil
{
    /**
     * Check if the proxy URL is a SOCKS5 proxy.
     */
    public static function isSocks5(string $proxyUrl): bool
    {
        return str_starts_with($proxyUrl, 'socks5://') || str_starts_with($proxyUrl, 'socks5h://');
    }

    /**
     * Get Guzzle client proxy configuration.
     * For SOCKS5 proxies, returns curl options; for HTTP/HTTPS proxies, returns standard proxy option.
     *
     * @return array{proxy?: string, curl?: array<int, mixed>}
     */
    public static function getGuzzleProxyConfig(?string $proxyUrl): array
    {
        if ($proxyUrl === null || $proxyUrl === '') {
            return [];
        }

        if (! self::isSocks5($proxyUrl)) {
            return ['proxy' => $proxyUrl];
        }

        return ['curl' => self::buildCurlProxyOptions($proxyUrl)];
    }

    /**
     * Get cURL proxy options array.
     * For use with native cURL calls.
     *
     * @return array<int, mixed>
     */
    public static function getCurlProxyOptions(?string $proxyUrl): array
    {
        if ($proxyUrl === null || $proxyUrl === '') {
            return [];
        }

        if (! self::isSocks5($proxyUrl)) {
            return [CURLOPT_PROXY => $proxyUrl];
        }

        return self::buildCurlProxyOptions($proxyUrl);
    }

    /**
     * Build SOCKS5 proxy cURL options.
     * Uses CURLPROXY_SOCKS5_HOSTNAME to ensure DNS resolution happens on the proxy server.
     *
     * @return array<int, mixed>
     */
    public static function buildCurlProxyOptions(string $proxyUrl): array
    {
        // Remove scheme prefix (socks5:// or socks5h://)
        $proxyWithoutScheme = preg_replace('/^socks5h?:\/\//', '', $proxyUrl);
        if ($proxyWithoutScheme === null || $proxyWithoutScheme === $proxyUrl) {
            // Not a SOCKS5 URL or regex failed, return standard proxy option
            return [CURLOPT_PROXY => $proxyUrl];
        }

        // Check for authentication info (username:password@host:port)
        if (str_contains($proxyWithoutScheme, '@')) {
            [$auth, $hostPort] = explode('@', $proxyWithoutScheme, 2);
            return [
                CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
                CURLOPT_PROXY => $hostPort,
                CURLOPT_PROXYUSERPWD => $auth,
            ];
        }

        return [
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5_HOSTNAME,
            CURLOPT_PROXY => $proxyWithoutScheme,
        ];
    }

    /**
     * Apply proxy configuration to cURL handle.
     */
    public static function applyCurlProxy(CurlHandle $ch, ?string $proxyUrl): void
    {
        if ($proxyUrl === null || $proxyUrl === '') {
            return;
        }

        $options = self::getCurlProxyOptions($proxyUrl);
        foreach ($options as $option => $value) {
            curl_setopt($ch, $option, $value);
        }
    }
}

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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Psr\Http\Message\RequestInterface;

/**
 * AWS Signature Version 4 implementation for signing HTTP requests.
 */
class AwsSignatureV4
{
    private const ISO8601_BASIC = 'Ymd\THis\Z';

    private const ALGORITHM = 'AWS4-HMAC-SHA256';

    private const SERVICE = 'bedrock';

    private const TERMINATOR = 'aws4_request';

    private string $accessKey;

    private string $secretKey;

    private string $region;

    private ?string $sessionToken;

    /**
     * Cache for derived signing keys.
     */
    private array $cache = [];

    private int $cacheSize = 0;

    /**
     * Headers that should not be signed.
     */
    private array $headerBlacklist = [
        'cache-control',
        'content-length',
        'expect',
        'max-forwards',
        'pragma',
        'range',
        'te',
        'if-match',
        'if-none-match',
        'if-modified-since',
        'if-unmodified-since',
        'if-range',
        'accept',
        'authorization',
        'proxy-authorization',
        'from',
        'referer',
        'user-agent',
        'x-amz-user-agent',
        'x-amzn-trace-id',
        'aws-sdk-invocation-id',
        'aws-sdk-retry',
    ];

    public function __construct(
        string $accessKey,
        string $secretKey,
        string $region,
        ?string $sessionToken = null
    ) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->sessionToken = $sessionToken;
    }

    /**
     * Sign a PSR-7 request with AWS Signature V4.
     */
    public function signRequest(RequestInterface $request): RequestInterface
    {
        // Get current timestamp
        $timestamp = gmdate(self::ISO8601_BASIC);
        $date = substr($timestamp, 0, 8); // YYYYMMDD

        // Add required headers
        $request = $request->withHeader('X-Amz-Date', $timestamp);
        $request = $request->withHeader('Host', $request->getUri()->getHost());

        if ($this->sessionToken) {
            $request = $request->withHeader('X-Amz-Security-Token', $this->sessionToken);
        }

        // Step 1: Create canonical request
        $canonicalRequest = $this->createCanonicalRequest($request);

        // Step 2: Create string to sign
        $credentialScope = $this->createCredentialScope($date);
        $stringToSign = $this->createStringToSign($timestamp, $credentialScope, $canonicalRequest);

        // Step 3: Calculate signature
        $signature = $this->calculateSignature($date, $stringToSign);

        // Step 4: Add authorization header
        $signedHeaders = $this->getSignedHeaders($request);
        $authorizationHeader = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return $request->withHeader('Authorization', $authorizationHeader);
    }

    /**
     * Create canonical request string.
     */
    private function createCanonicalRequest(RequestInterface $request): string
    {
        $method = $request->getMethod();
        $uri = $this->getCanonicalUri($request);
        $queryString = $this->getCanonicalQueryString($request);
        $headers = $this->getCanonicalHeaders($request);
        $signedHeaders = $this->getSignedHeaders($request);
        $payload = $this->getPayloadHash($request);

        return implode("\n", [
            $method,
            $uri,
            $queryString,
            $headers,
            $signedHeaders,
            $payload,
        ]);
    }

    /**
     * Get canonical URI from request.
     */
    private function getCanonicalUri(RequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        if (empty($path)) {
            return '/';
        }

        // Encode the path, but preserve forward slashes
        $encoded = rawurlencode(ltrim($path, '/'));
        return '/' . str_replace('%2F', '/', $encoded);
    }

    /**
     * Get canonical query string from request.
     */
    private function getCanonicalQueryString(RequestInterface $request): string
    {
        $query = $request->getUri()->getQuery();
        if (empty($query)) {
            return '';
        }

        parse_str($query, $params);
        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                sort($value);
                foreach ($value as $v) {
                    $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $v);
                }
            } else {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($value !== null ? (string) $value : '');
            }
        }

        return implode('&', $parts);
    }

    /**
     * Get canonical headers string.
     */
    private function getCanonicalHeaders(RequestInterface $request): string
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $name = strtolower((string) $name);
            if ($this->shouldSignHeader($name)) {
                $value = implode(',', $values);
                // Normalize whitespace
                $value = preg_replace('/\s+/', ' ', trim($value));
                $headers[$name] = $name . ':' . $value;
            }
        }

        ksort($headers);
        return implode("\n", $headers) . "\n";
    }

    /**
     * Get signed headers list.
     */
    private function getSignedHeaders(RequestInterface $request): string
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $name = strtolower((string) $name);
            if ($this->shouldSignHeader($name)) {
                $headers[] = $name;
            }
        }

        sort($headers);
        return implode(';', $headers);
    }

    /**
     * Check if header should be signed.
     */
    private function shouldSignHeader(string $headerName): bool
    {
        return ! in_array($headerName, $this->headerBlacklist, true);
    }

    /**
     * Get payload hash (SHA256 of request body).
     */
    private function getPayloadHash(RequestInterface $request): string
    {
        // For HTTPS streaming requests, can use UNSIGNED-PAYLOAD
        // For regular requests, compute SHA256 hash of body
        $body = (string) $request->getBody();
        $request->getBody()->rewind();
        return hash('sha256', $body);
    }

    /**
     * Create credential scope.
     */
    private function createCredentialScope(string $date): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            $date,
            $this->region,
            self::SERVICE,
            self::TERMINATOR
        );
    }

    /**
     * Create string to sign.
     */
    private function createStringToSign(
        string $timestamp,
        string $credentialScope,
        string $canonicalRequest
    ): string {
        $hashedRequest = hash('sha256', $canonicalRequest);

        return implode("\n", [
            self::ALGORITHM,
            $timestamp,
            $credentialScope,
            $hashedRequest,
        ]);
    }

    /**
     * Calculate signature using derived signing key.
     */
    private function calculateSignature(string $date, string $stringToSign): string
    {
        $signingKey = $this->getSigningKey($date);
        return hash_hmac('sha256', $stringToSign, $signingKey);
    }

    /**
     * Derive signing key with caching.
     */
    private function getSigningKey(string $date): string
    {
        $cacheKey = $date . '_' . $this->region . '_' . self::SERVICE . '_' . $this->secretKey;

        if (! isset($this->cache[$cacheKey])) {
            // Clear the cache when it reaches 50 entries
            if (++$this->cacheSize > 50) {
                $this->cache = [];
                $this->cacheSize = 0;
            }

            $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
            $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
            $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
            $kSigning = hash_hmac('sha256', self::TERMINATOR, $kService, true);

            $this->cache[$cacheKey] = $kSigning;
        }

        return $this->cache[$cacheKey];
    }
}

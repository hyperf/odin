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

use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use RuntimeException;

class OdinSimpleCurl
{
    /**
     * Send request using SimpleCURLClient stream wrapper.
     *
     * @param string $url Request URL
     * @param array $options Request options (headers, json, timeout, etc.)
     * @param bool $skipContentTypeCheck Skip Content-Type validation (for non-SSE streams like AWS EventStream)
     * @return Response Returns Response with stream as body
     * @throws LLMConnectionTimeoutException If connection timeout or no valid HTTP response
     * @throws LLMReadTimeoutException If operation timeout
     * @throws LLMNetworkException If network connection error
     * @throws LLMInvalidRequestException If HTTP 4xx client error or invalid content-type
     * @throws LLMApiException If HTTP 5xx server error
     * @throws RuntimeException If stream creation fails
     */
    public static function send(string $url, array $options, bool $skipContentTypeCheck = false): Response
    {
        $options['url'] = $url;

        // Attempt to open stream with error suppression to handle exceptions properly
        $stream = @fopen('OdinSimpleCurl://' . json_encode($options), 'r', false);

        if ($stream === false) {
            $error = error_get_last();
            throw new RuntimeException(
                'Failed to open SimpleCURL stream: ' . ($error['message'] ?? 'Unknown error')
            );
        }

        $metadata = stream_get_meta_data($stream);
        $wrapper = $metadata['wrapper_data'] ?? null;

        if (! $wrapper instanceof SimpleCURLClient) {
            fclose($stream);
            throw new RuntimeException('Invalid stream wrapper: expected SimpleCURLClient instance');
        }

        $metadataInfo = $wrapper->stream_metadata();
        $statusCode = $metadataInfo['http_code'] ?? 0;
        $responseHeaders = $metadataInfo['headers'] ?? [];

        // Check for cURL errors
        if (isset($metadataInfo['error'])) {
            fclose($stream);
            $curlCode = $metadataInfo['error_code'] ?? 0;
            $errorMessage = $metadataInfo['error'];
            
            // Map cURL error codes to appropriate LLM exceptions
            // Common cURL error codes:
            // 6: Could not resolve host
            // 7: Failed to connect
            // 28: Operation timeout
            // 35: SSL/TLS connection error
            // 52: Empty reply from server
            // 56: Failure in receiving network data
            
            if ($curlCode === 28) {
                // Operation timeout
                throw new LLMReadTimeoutException(
                    "Connection timeout: {$errorMessage}",
                    new RuntimeException($errorMessage, $curlCode)
                );
            }
            
            if (in_array($curlCode, [6, 7, 52, 56])) {
                // Connection or network errors
                throw new LLMNetworkException(
                    "Network connection error: {$errorMessage}",
                    $curlCode,
                    new RuntimeException($errorMessage, $curlCode)
                );
            }
            
            if ($curlCode === 35) {
                // SSL/TLS error
                throw new LLMNetworkException(
                    "SSL/TLS error: {$errorMessage}",
                    $curlCode,
                    new RuntimeException($errorMessage, $curlCode)
                );
            }
            
            // Default to network exception for other cURL errors
            throw new LLMNetworkException(
                "HTTP request failed: {$errorMessage} (code: {$curlCode})",
                $curlCode,
                new RuntimeException($errorMessage, $curlCode)
            );
        }

        // Validate HTTP status code
        if ($statusCode === 0) {
            fclose($stream);
            throw new LLMConnectionTimeoutException(
                'Connection error: No valid HTTP response received from server',
                new RuntimeException('Invalid HTTP status code: 0')
            );
        }

        // Check for HTTP error status codes (4xx, 5xx)
        if ($statusCode >= 400) {
            // Read error response body
            $errorBody = stream_get_contents($stream);
            fclose($stream);

            $errorMessage = "HTTP {$statusCode} error";

            // Try to parse JSON error response
            if (! empty($errorBody)) {
                $errorData = @json_decode($errorBody, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($errorData['error'])) {
                    // OpenAI/Claude style error format
                    if (is_array($errorData['error'])) {
                        $errorMessage .= ": {$errorData['error']['message']}";
                    } else {
                        $errorMessage .= ": {$errorData['error']}";
                    }
                } elseif (! empty($errorBody)) {
                    // Include raw error body (truncated if too long)
                    $truncatedBody = strlen($errorBody) > 200
                        ? substr($errorBody, 0, 200) . '...'
                        : $errorBody;
                    $errorMessage .= ": {$truncatedBody}";
                }
            }

            // Map HTTP status codes to appropriate LLM exceptions
            if ($statusCode >= 500) {
                // Server errors (5xx)
                throw new LLMApiException(
                    $errorMessage,
                    $statusCode,
                    new RuntimeException($errorMessage, $statusCode),
                    0,
                    $statusCode
                );
            }
            
            // Client errors (4xx)
            throw new LLMInvalidRequestException(
                $errorMessage,
                new RuntimeException($errorMessage, $statusCode),
                $statusCode
            );
        }

        // Verify content-type for streaming response (skip for special formats like AWS EventStream)
        if (! $skipContentTypeCheck) {
            $contentType = $responseHeaders['content-type'] ?? '';
            if (! empty($contentType) && ! str_contains($contentType, 'text/event-stream')) {
                // Not a SSE stream, read the full response
                $body = stream_get_contents($stream);
                fclose($stream);

                $errorMessage = "Expected 'text/event-stream' response but got '{$contentType}'. Response: "
                    . (strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body);
                
                throw new LLMInvalidRequestException(
                    $errorMessage,
                    new RuntimeException($errorMessage),
                    400
                );
            }
        }

        return new Response($statusCode, $responseHeaders, $stream);
    }
}

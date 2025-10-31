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
     * @throws RuntimeException If stream creation fails or connection error occurs
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
            throw new RuntimeException(
                "HTTP request failed: {$metadataInfo['error']} (code: {$metadataInfo['error_code']})"
            );
        }

        // Validate HTTP status code
        if ($statusCode === 0) {
            fclose($stream);
            throw new RuntimeException('Invalid HTTP status code: connection may have failed');
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

            throw new RuntimeException($errorMessage);
        }

        // Verify content-type for streaming response (skip for special formats like AWS EventStream)
        if (! $skipContentTypeCheck) {
            $contentType = $responseHeaders['content-type'] ?? '';
            if (! empty($contentType) && ! str_contains($contentType, 'text/event-stream')) {
                // Not a SSE stream, read the full response
                $body = stream_get_contents($stream);
                fclose($stream);

                throw new RuntimeException(
                    "Expected 'text/event-stream' response but got '{$contentType}'. Response: "
                    . (strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body)
                );
            }
        }

        return new Response($statusCode, $responseHeaders, $stream);
    }
}

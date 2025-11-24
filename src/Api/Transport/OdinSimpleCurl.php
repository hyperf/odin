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
use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use Hyperf\Odin\Exception\LLMException\Network\LLMReadTimeoutException;
use Hyperf\Odin\Exception\RuntimeException;

class OdinSimpleCurl
{
    public static function send(string $url, array $options, bool $skipContentTypeCheck = false): Response
    {
        $options['url'] = $url;

        $stream = @fopen('OdinSimpleCurl://' . json_encode($options), 'r', false);

        if ($stream === false) {
            $error = error_get_last();
            throw new LLMNetworkException(
                'Failed to open SimpleCURL stream: ' . ($error['message'] ?? 'Unknown error')
            );
        }

        $metadata = stream_get_meta_data($stream);
        $wrapper = $metadata['wrapper_data'] ?? null;

        if (! $wrapper instanceof SimpleCURLClient) {
            fclose($stream);
            throw new LLMConfigurationException('Invalid stream wrapper: expected SimpleCURLClient instance');
        }

        $metadataInfo = $wrapper->stream_metadata();
        $statusCode = $metadataInfo['http_code'] ?? 0;
        $responseHeaders = $metadataInfo['headers'] ?? [];

        if (isset($metadataInfo['error'])) {
            fclose($stream);
            $curlCode = $metadataInfo['error_code'] ?? 0;
            $errorMessage = $metadataInfo['error'];

            if ($curlCode === 28) {
                throw new LLMReadTimeoutException(
                    "Connection timeout: {$errorMessage}",
                    new RuntimeException($errorMessage, $curlCode)
                );
            }

            if (in_array($curlCode, [6, 7, 52, 56])) {
                throw new LLMNetworkException(
                    "Network connection error: {$errorMessage}",
                    $curlCode,
                    new RuntimeException($errorMessage, $curlCode)
                );
            }

            if ($curlCode === 35) {
                throw new LLMNetworkException(
                    "SSL/TLS error: {$errorMessage}",
                    $curlCode,
                    new RuntimeException($errorMessage, $curlCode)
                );
            }

            throw new LLMNetworkException(
                "HTTP request failed: {$errorMessage} (code: {$curlCode})",
                $curlCode,
                new RuntimeException($errorMessage, $curlCode)
            );
        }

        if ($statusCode === 0) {
            fclose($stream);
            throw new LLMConnectionTimeoutException(
                'Connection error: No valid HTTP response received from server',
                new RuntimeException('Invalid HTTP status code: 0')
            );
        }

        if ($statusCode >= 400) {
            $errorBody = stream_get_contents($stream);
            fclose($stream);

            $errorMessage = "HTTP {$statusCode} error";

            if (! empty($errorBody)) {
                $errorData = @json_decode($errorBody, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($errorData['error'])) {
                    if (is_array($errorData['error'])) {
                        $errorMessage .= ": {$errorData['error']['message']}";
                    } else {
                        $errorMessage .= ": {$errorData['error']}";
                    }
                } elseif (! empty($errorBody)) {
                    $truncatedBody = strlen($errorBody) > 200
                        ? substr($errorBody, 0, 200) . '...'
                        : $errorBody;
                    $errorMessage .= ": {$truncatedBody}";
                }
            }

            if ($statusCode >= 500) {
                throw new LLMApiException(
                    $errorMessage,
                    $statusCode,
                    new RuntimeException($errorMessage, $statusCode),
                    0,
                    $statusCode
                );
            }

            throw new LLMInvalidRequestException(
                $errorMessage,
                new RuntimeException($errorMessage, $statusCode),
                $statusCode
            );
        }

        if (! $skipContentTypeCheck) {
            $contentType = $responseHeaders['content-type'] ?? '';
            if (! empty($contentType) && ! str_contains($contentType, 'text/event-stream')) {
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

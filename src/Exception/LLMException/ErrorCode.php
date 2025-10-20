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

namespace Hyperf\Odin\Exception\LLMException;

/**
 * LLM error code definitions.
 */
class ErrorCode
{
    /**
     * Error type base values.
     */
    public const CONFIG_ERROR_BASE = 1000;

    public const NETWORK_ERROR_BASE = 2000;

    public const API_ERROR_BASE = 3000;

    public const MODEL_ERROR_BASE = 4000;

    /**
     * 配置错误码 (1000-1999).
     */
    public const CONFIG_INVALID_API_KEY = self::CONFIG_ERROR_BASE + 1;

    public const CONFIG_INVALID_ENDPOINT = self::CONFIG_ERROR_BASE + 2;

    public const CONFIG_INVALID_MODEL = self::CONFIG_ERROR_BASE + 3;

    public const CONFIG_INVALID_PARAMETER = self::CONFIG_ERROR_BASE + 4;

    /**
     * 网络错误码 (2000-2999).
     */
    public const NETWORK_CONNECTION_TIMEOUT = self::NETWORK_ERROR_BASE + 1;

    public const NETWORK_READ_TIMEOUT = self::NETWORK_ERROR_BASE + 2;

    public const NETWORK_WRITE_TIMEOUT = self::NETWORK_ERROR_BASE + 3;

    public const NETWORK_CONNECTION_ERROR = self::NETWORK_ERROR_BASE + 4;

    public const NETWORK_SSL_ERROR = self::NETWORK_ERROR_BASE + 5;

    /**
     * API错误码 (3000-3999).
     */
    public const API_RATE_LIMIT = self::API_ERROR_BASE + 1;

    public const API_INVALID_REQUEST = self::API_ERROR_BASE + 2;

    public const API_SERVER_ERROR = self::API_ERROR_BASE + 3;

    public const API_AUTHENTICATION_ERROR = self::API_ERROR_BASE + 4;

    public const API_PERMISSION_DENIED = self::API_ERROR_BASE + 5;

    public const API_QUOTA_EXCEEDED = self::API_ERROR_BASE + 6;

    /**
     * 模型错误码 (4000-4999).
     */
    public const MODEL_CONTENT_FILTER = self::MODEL_ERROR_BASE + 1;

    public const MODEL_CONTEXT_LENGTH = self::MODEL_ERROR_BASE + 2;

    public const MODEL_FUNCTION_CALL_NOT_SUPPORTED = self::MODEL_ERROR_BASE + 3;

    public const MODEL_MULTI_MODAL_NOT_SUPPORTED = self::MODEL_ERROR_BASE + 4;

    public const MODEL_EMBEDDING_NOT_SUPPORTED = self::MODEL_ERROR_BASE + 5;

    public const MODEL_IMAGE_URL_ACCESS_ERROR = self::MODEL_ERROR_BASE + 6;

    public const MODEL_EMBEDDING_INPUT_TOO_LARGE = self::MODEL_ERROR_BASE + 7;

    /**
     * Error code mapping table.
     */
    public static function getErrorMessages(): array
    {
        return [
            // Configuration errors
            self::CONFIG_INVALID_API_KEY => ErrorMessage::INVALID_API_KEY,
            self::CONFIG_INVALID_ENDPOINT => ErrorMessage::INVALID_ENDPOINT,
            self::CONFIG_INVALID_MODEL => ErrorMessage::INVALID_MODEL,
            self::CONFIG_INVALID_PARAMETER => ErrorMessage::INVALID_PARAMETER,

            // Network errors
            self::NETWORK_CONNECTION_TIMEOUT => ErrorMessage::CONNECTION_TIMEOUT,
            self::NETWORK_READ_TIMEOUT => ErrorMessage::READ_TIMEOUT,
            self::NETWORK_WRITE_TIMEOUT => ErrorMessage::WRITE_TIMEOUT,
            self::NETWORK_CONNECTION_ERROR => ErrorMessage::CONNECTION_ERROR,
            self::NETWORK_SSL_ERROR => ErrorMessage::SSL_ERROR,

            // API errors
            self::API_RATE_LIMIT => ErrorMessage::RATE_LIMIT,
            self::API_INVALID_REQUEST => ErrorMessage::INVALID_REQUEST,
            self::API_SERVER_ERROR => ErrorMessage::SERVER_ERROR,
            self::API_AUTHENTICATION_ERROR => ErrorMessage::AUTHENTICATION_ERROR,
            self::API_PERMISSION_DENIED => ErrorMessage::PERMISSION_DENIED,
            self::API_QUOTA_EXCEEDED => ErrorMessage::QUOTA_EXCEEDED,

            // Model errors
            self::MODEL_CONTENT_FILTER => ErrorMessage::CONTENT_FILTER,
            self::MODEL_CONTEXT_LENGTH => ErrorMessage::CONTEXT_LENGTH,
            self::MODEL_FUNCTION_CALL_NOT_SUPPORTED => ErrorMessage::FUNCTION_NOT_SUPPORTED,
            self::MODEL_MULTI_MODAL_NOT_SUPPORTED => ErrorMessage::MULTIMODAL_NOT_SUPPORTED,
            self::MODEL_EMBEDDING_NOT_SUPPORTED => ErrorMessage::EMBEDDING_NOT_SUPPORTED,
            self::MODEL_IMAGE_URL_ACCESS_ERROR => ErrorMessage::IMAGE_URL_ACCESS,
            self::MODEL_EMBEDDING_INPUT_TOO_LARGE => ErrorMessage::EMBEDDING_INPUT_TOO_LARGE,
        ];
    }

    /**
     * Get error message.
     */
    public static function getMessage(int $code): string
    {
        $messages = self::getErrorMessages();
        return $messages[$code] ?? ErrorMessage::UNKNOWN_ERROR;
    }

    /**
     * Get error suggestion.
     */
    public static function getSuggestion(int $code): string
    {
        $suggestions = [
            // Configuration error suggestions
            self::CONFIG_INVALID_API_KEY => 'Please check your API key configuration or contact the service provider for a valid API key',
            self::CONFIG_INVALID_ENDPOINT => 'Please verify the API endpoint URL is correct and includes the protocol prefix (http/https)',
            self::CONFIG_INVALID_MODEL => 'Please verify the model name is correct or check the list of available models',

            // Network error suggestions
            self::NETWORK_CONNECTION_TIMEOUT => 'Please check your network connection or increase the connection timeout, then retry',
            self::NETWORK_READ_TIMEOUT => 'Please increase the read timeout or reduce request complexity, then retry',

            // API error suggestions
            self::API_RATE_LIMIT => 'Please reduce request frequency, implement rate limiting, or wait before retrying',
            self::API_QUOTA_EXCEEDED => 'Please check your account quota or upgrade your account plan',

            // Model error suggestions
            self::MODEL_CONTEXT_LENGTH => 'Please reduce input length or use a model that supports longer context',
            self::MODEL_FUNCTION_CALL_NOT_SUPPORTED => 'Please select a model that supports function calling',
            self::MODEL_MULTI_MODAL_NOT_SUPPORTED => 'Please select a model that supports multimodal input',
            self::MODEL_IMAGE_URL_ACCESS_ERROR => 'Please verify the image URL is correct, publicly accessible, and in a supported format',
        ];

        return $suggestions[$code] ?? 'Please check input parameters and configuration. If the issue persists, contact technical support';
    }
}

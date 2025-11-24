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
 * LLM error message constants.
 *
 * Centralized management of all error messages for better maintainability.
 */
class ErrorMessage
{
    /**
     * Configuration error messages.
     */
    public const INVALID_API_KEY = 'Invalid or missing API key';

    public const INVALID_ENDPOINT = 'Invalid API endpoint URL';

    public const INVALID_MODEL = 'Invalid model name or model unavailable';

    public const INVALID_PARAMETER = 'Invalid configuration parameter';

    /**
     * Network error messages.
     */
    public const CONNECTION_TIMEOUT = 'Connection to LLM service timed out';

    public const READ_TIMEOUT = 'Reading response from LLM service timed out';

    public const WRITE_TIMEOUT = 'Sending request to LLM service timed out';

    public const CONNECTION_ERROR = 'Failed to connect to LLM service';

    public const SSL_ERROR = 'SSL/TLS connection error';

    public const NETWORK_REQUEST_ERROR = 'LLM network request error';

    public const NETWORK_CONNECTION_ERROR = 'LLM network connection error';

    public const RESOLVE_HOST_ERROR = 'Unable to resolve LLM service hostname';

    /**
     * API error messages.
     */
    public const RATE_LIMIT = 'API rate limit exceeded';

    public const INVALID_REQUEST = 'Invalid API request';

    public const SERVER_ERROR = 'LLM service error';

    public const CLIENT_ERROR = 'LLM client request error';

    public const AUTHENTICATION_ERROR = 'API authentication failed';

    public const PERMISSION_DENIED = 'API permission denied';

    public const QUOTA_EXCEEDED = 'API quota exceeded';

    /**
     * Model error messages.
     */
    public const CONTENT_FILTER = 'Content filtered by safety system';

    public const CONTEXT_LENGTH = 'Context length exceeds model limit';

    public const FUNCTION_NOT_SUPPORTED = 'Model does not support function calling';

    public const MULTIMODAL_NOT_SUPPORTED = 'Model does not support multimodal input';

    public const EMBEDDING_NOT_SUPPORTED = 'Model does not support embedding generation';

    public const IMAGE_URL_ACCESS = 'Multimodal image URL is not accessible';

    public const EMBEDDING_INPUT_TOO_LARGE = 'Embedding input exceeds model processing limit';

    public const UNSUPPORTED_IMAGE_FORMAT = 'Unsupported image format';

    public const MODEL_INVALID_CONTENT = 'Model produced invalid content';

    /**
     * Stream error messages.
     */
    public const STREAM_TIMEOUT = 'Stream response timed out';

    public const FIRST_CHUNK_TIMEOUT = 'Waiting for first stream chunk timed out';

    /**
     * Azure specific messages.
     */
    public const AZURE_UNAVAILABLE = 'Azure OpenAI service temporarily unavailable, please retry later';

    /**
     * Generic messages.
     */
    public const UNKNOWN_ERROR = 'Unknown error';

    public const LLM_INVOCATION_ERROR = 'LLM invocation error';
}

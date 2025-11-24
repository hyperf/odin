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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Exception\LLMException\Api\LLMRateLimitException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContentFilterException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContextLengthException;
use Hyperf\Odin\Exception\LLMException\Model\LLMEmbeddingInputTooLargeException;
use Hyperf\Odin\Exception\LLMException\Model\LLMImageUrlAccessException;
use Hyperf\Odin\Exception\LLMException\Network\LLMConnectionTimeoutException;
use Throwable;

/**
 * 错误映射配置.
 * 用于将各种外部异常映射为系统内部统一的LLM异常.
 */
class ErrorMapping
{
    /**
     * 映射关系配置.
     *
     * 格式: [
     *     '异常类名' => [
     *         'regex' => '匹配正则表达式',
     *         'factory' => function(Throwable $e) { return new LLMException(); },
     *     ],
     * ]
     */
    public static function getDefaultMapping(): array
    {
        return [
            // Connection timeout exception
            ConnectException::class => [
                // Connection timeout exception
                [
                    'regex' => '/timeout|timed\s+out/i',
                    'factory' => function (Throwable $e) {
                        $message = $e->getMessage();
                        // 尝试从消息中提取超时时间
                        preg_match('/(\d+(?:\.\d+)?)\s*s/i', $message, $matches);
                        $timeout = isset($matches[1]) ? (float) $matches[1] : null;
                        $statusCode = ($e instanceof RequestException && $e->getResponse()) ? $e->getResponse()->getStatusCode() : 408;
                        return new LLMConnectionTimeoutException(ErrorMessage::CONNECTION_TIMEOUT, $e, $timeout, $statusCode);
                    },
                ],
                // Unable to resolve hostname exception
                [
                    'regex' => '/Could not resolve host/i',
                    'factory' => function (Throwable $e) {
                        $message = $e->getMessage();
                        // 尝试从消息中提取主机名
                        preg_match('/Could not resolve host: ([^\s\(\)]+)/i', $message, $matches);
                        $hostname = $matches[1] ?? 'unknown host';
                        return new LLMNetworkException(
                            sprintf('%s: %s', ErrorMessage::RESOLVE_HOST_ERROR, $hostname),
                            4,
                            $e,
                            ErrorCode::NETWORK_CONNECTION_ERROR
                        );
                    },
                ],
                // Default network connection exception handling
                [
                    'default' => true,
                    'factory' => function (Throwable $e) {
                        return new LLMNetworkException(
                            sprintf('%s: %s', ErrorMessage::NETWORK_CONNECTION_ERROR, $e->getMessage()),
                            4,
                            $e,
                            ErrorCode::NETWORK_CONNECTION_ERROR
                        );
                    },
                ],
            ],

            // Request exception
            RequestException::class => [
                // Invalid API key (supports both English and Chinese)
                [
                    'regex' => '/invalid.+api.+key|api.+key.+invalid|authentication|unauthorized|invalid.+missing.+api.+key|API密钥无效/i',
                    'status' => [401, 403],
                    'factory' => function (RequestException $e) {
                        $provider = '';
                        $message = ErrorMessage::INVALID_API_KEY;

                        if ($e->getRequest()->getUri()->getHost()) {
                            $provider = $e->getRequest()->getUri()->getHost();
                        }

                        // Extract message from response body
                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $body = $response->getBody();
                            if ($body->isSeekable()) {
                                $body->rewind();
                            }
                            $responseBody = (string) $body;
                            $data = json_decode($responseBody, true);
                            if (is_array($data)) {
                                if (isset($data['error']['message'])) {
                                    $message = $data['error']['message'];
                                } elseif (isset($data['message'])) {
                                    $message = $data['message'];
                                }
                            }
                        }

                        return new LLMInvalidApiKeyException($message, $e, $provider);
                    },
                ],
                // Rate limit (supports both English and Chinese)
                [
                    'regex' => '/rate\s+limit|too\s+many\s+requests|API请求频率超出限制|rate.+limit.+exceeded/i',
                    'status' => [429],
                    'factory' => function (RequestException $e) {
                        $retryAfter = null;
                        $message = ErrorMessage::RATE_LIMIT;

                        if ($e->getResponse()) {
                            $retryAfter = $e->getResponse()->getHeaderLine('Retry-After');
                            $retryAfter = $retryAfter ? (int) $retryAfter : null;

                            // Extract message from response body
                            $response = $e->getResponse();
                            $body = $response->getBody();
                            if ($body->isSeekable()) {
                                $body->rewind();
                            }
                            $responseBody = (string) $body;
                            $data = json_decode($responseBody, true);
                            if (is_array($data)) {
                                if (isset($data['error']['message'])) {
                                    $message = $data['error']['message'];
                                } elseif (isset($data['message'])) {
                                    $message = $data['message'];
                                }
                            }
                        }

                        return new LLMRateLimitException($message, $e, 429, $retryAfter);
                    },
                ],
                // Azure OpenAI model content filter error
                [
                    'regex' => '/model\s+produced\s+invalid\s+content|model_error/i',
                    'status' => [500],
                    'factory' => function (RequestException $e) {
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 500;
                        $body = '';
                        $errorType = 'model_error';
                        $suggestion = '';

                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $response->getBody()->rewind(); // 重置流位置
                            $body = $response->getBody()->getContents();
                            $data = json_decode($body, true);
                            if (isset($data['error'])) {
                                $errorType = $data['error']['type'] ?? 'model_error';
                                if (isset($data['error']['message']) && str_contains($data['error']['message'], 'modifying your prompt')) {
                                    $suggestion = 'Please modify your prompt content';
                                }
                            }
                        }

                        $message = ErrorMessage::MODEL_INVALID_CONTENT;
                        if ($suggestion) {
                            $message .= ', ' . $suggestion;
                        }

                        return new LLMContentFilterException($message, $e, null, [$errorType], $statusCode);
                    },
                ],
                // Embedding input too large error
                [
                    'regex' => '/input\s+is\s+too\s+large|input\s+too\s+large|input\s+size\s+exceeds|batch\s+size\s+too\s+large|increase.+batch.+size/i',
                    'status' => [400, 413, 500],
                    'factory' => function (RequestException $e) {
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                        $model = null;
                        $inputLength = null;
                        $maxInputLength = null;

                        // 尝试从请求中提取模型名称
                        if ($e->getRequest() && $e->getRequest()->getBody()) {
                            $requestBody = (string) $e->getRequest()->getBody();
                            $data = json_decode($requestBody, true);
                            if (isset($data['model'])) {
                                $model = $data['model'];
                            }

                            // 尝试估算输入长度
                            if (isset($data['input'])) {
                                if (is_string($data['input'])) {
                                    $inputLength = mb_strlen($data['input'], 'UTF-8');
                                } elseif (is_array($data['input'])) {
                                    $inputLength = array_sum(array_map(function ($item) {
                                        return is_string($item) ? mb_strlen($item, 'UTF-8') : 0;
                                    }, $data['input']));
                                }
                            }
                        }

                        // 尝试从错误响应中提取更多信息
                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $response->getBody()->rewind(); // 重置流位置
                            $body = $response->getBody()->getContents();
                            $data = json_decode($body, true);
                            if (isset($data['error']['message'])) {
                                // 尝试从错误消息中提取数字限制
                                preg_match('/(\d+)/', $data['error']['message'], $matches);
                                if (! empty($matches[1])) {
                                    $maxInputLength = (int) $matches[1];
                                }
                            }
                        }

                        $message = ErrorMessage::EMBEDDING_INPUT_TOO_LARGE;
                        if ($model) {
                            $message .= " (model: {$model})";
                        }

                        return new LLMEmbeddingInputTooLargeException(
                            $message,
                            $e,
                            $model,
                            $inputLength,
                            $maxInputLength,
                            $statusCode
                        );
                    },
                ],
                // Azure OpenAI server internal error (retryable network error)
                [
                    'regex' => '/server\s+had\s+an\s+error|server_error/i',
                    'status' => [500, 502, 503, 504],
                    'factory' => function (RequestException $e) {
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 500;
                        return new LLMNetworkException(
                            ErrorMessage::AZURE_UNAVAILABLE,
                            4,
                            $e,
                            ErrorCode::NETWORK_CONNECTION_ERROR,
                            $statusCode
                        );
                    },
                ],
                // Content filter (supports both English and Chinese)
                [
                    'regex' => '/content\s+filter|content\s+policy|inappropriate|unsafe content|violate|policy|内容被系统安全过滤|filtered.+safety.+system/i',
                    'factory' => function (RequestException $e) {
                        $labels = null;
                        $message = ErrorMessage::CONTENT_FILTER;

                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $response->getBody()->rewind(); // 重置流位置
                            $body = $response->getBody()->getContents();
                            $data = json_decode($body, true);

                            // Extract message from response
                            if (is_array($data)) {
                                if (isset($data['error']['message'])) {
                                    $message = $data['error']['message'];
                                } elseif (isset($data['message'])) {
                                    $message = $data['message'];
                                }

                                // Extract content filter labels if available
                                if (isset($data['error']['content_filter_results'])) {
                                    $labels = array_keys($data['error']['content_filter_results']);
                                }
                            }
                        }

                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                        return new LLMContentFilterException($message, $e, null, $labels, $statusCode);
                    },
                ],
                // Context length exceeded (supports both English and Chinese)
                [
                    'regex' => '/context\s+length|token\s+limit|maximum\s+context\s+length|input\s+is\s+too\s+long|input\s+too\s+long|上下文长度超出模型限制|context.+exceeds.+limit|exceeds.+model.+limit/i',
                    'factory' => function (RequestException $e) {
                        $currentLength = null;
                        $maxLength = null;
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                        $message = null;

                        // Try to extract message from response body for proxy scenarios
                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $body = $response->getBody();
                            if ($body->isSeekable()) {
                                $body->rewind();
                            }
                            $responseBody = (string) $body;
                            $decodedBody = json_decode($responseBody, true);
                            if (is_array($decodedBody)) {
                                // Support both formats:
                                // 1. {"error": {"message": "...", "code": 4002}}
                                // 2. {"code": 4017, "message": "..."}
                                if (isset($decodedBody['error']['message'])) {
                                    $message = $decodedBody['error']['message'];
                                } elseif (isset($decodedBody['message'])) {
                                    $message = $decodedBody['message'];
                                }
                            }
                        }

                        // Fallback to exception message
                        if (! $message) {
                            $message = $e->getMessage();
                        }

                        // Try to extract length information from message
                        // Support multiple formats:
                        // 1. "8000 / 4096" or "8000/4096"
                        // 2. "current length: 8000, max limit: 4096"
                        // 3. "当前长度: 8000，最大限制: 4096" (Chinese, legacy support)
                        if (preg_match('/(\d+)\s*\/\s*(\d+)/i', $message, $matches)) {
                            $currentLength = (int) $matches[1];
                            $maxLength = (int) $matches[2];
                        } elseif (preg_match('/当前长度[：:]\s*(\d+).*最大限制[：:]\s*(\d+)/i', $message, $matches)) {
                            $currentLength = (int) $matches[1];
                            $maxLength = (int) $matches[2];
                        } elseif (preg_match('/current\s+length[：:]\s*(\d+).*max\s+limit[：:]\s*(\d+)/i', $message, $matches)) {
                            $currentLength = (int) $matches[1];
                            $maxLength = (int) $matches[2];
                        }

                        return new LLMContextLengthException($message ?: ErrorMessage::CONTEXT_LENGTH, $e, null, $currentLength, $maxLength, $statusCode);
                    },
                ],
                // Multimodal image URL not accessible (supports both English and Chinese)
                [
                    'regex' => '/image\s+url\s+is\s+not\s+accessible|invalid\s+image\s+url|image\s+could\s+not\s+be\s+accessed/i',
                    'factory' => function (RequestException $e) {
                        $imageUrl = null;
                        // 尝试从请求体或错误消息中提取图片URL
                        if ($e->getRequest() && $e->getRequest()->getBody()) {
                            $requestBody = (string) $e->getRequest()->getBody();
                            $data = json_decode($requestBody, true);
                            if (isset($data['messages'])) {
                                foreach ($data['messages'] as $message) {
                                    if (isset($message['content']) && is_array($message['content'])) {
                                        foreach ($message['content'] as $content) {
                                            if (isset($content['type']) && $content['type'] === 'image_url'
                                                && isset($content['image_url']['url'])) {
                                                $imageUrl = $content['image_url']['url'];
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                        return new LLMImageUrlAccessException(ErrorMessage::IMAGE_URL_ACCESS, $e, null, $imageUrl, $statusCode);
                    },
                ],
                // Invalid request (more precise matching to avoid model error mismatch)
                [
                    'regex' => '/invalid\s+(request|parameter|api|endpoint)|bad\s+request|malformed/i',
                    'status' => [400],
                    'factory' => function (RequestException $e) {
                        $invalidFields = null;
                        $providerErrorDetails = null;

                        if ($e->getResponse()) {
                            $response = $e->getResponse();
                            $response->getBody()->rewind(); // 重置流位置
                            $body = $response->getBody()->getContents();
                            $data = json_decode($body, true);

                            // 提取无效字段信息（保持原有逻辑）
                            if (isset($data['error']['param'])) {
                                $invalidFields = [$data['error']['param'] => $data['error']['message'] ?? '无效参数'];
                            }

                            // 提取完整的服务商错误详情
                            if (isset($data['error']) && is_array($data['error'])) {
                                $providerErrorDetails = [];

                                // 提取错误码
                                if (isset($data['error']['code'])) {
                                    $providerErrorDetails['code'] = $data['error']['code'];
                                }

                                // 提取错误消息
                                if (isset($data['error']['message'])) {
                                    $providerErrorDetails['message'] = $data['error']['message'];
                                }

                                // 提取错误类型
                                if (isset($data['error']['type'])) {
                                    $providerErrorDetails['type'] = $data['error']['type'];
                                }

                                // 提取参数字段
                                if (isset($data['error']['param'])) {
                                    $providerErrorDetails['param'] = $data['error']['param'];
                                }

                                // 如果有其他字段，也一并保存
                                foreach ($data['error'] as $key => $value) {
                                    if (! in_array($key, ['code', 'message', 'type', 'param']) && is_scalar($value)) {
                                        $providerErrorDetails[$key] = $value;
                                    }
                                }
                            }
                        }

                        return new LLMInvalidRequestException(ErrorMessage::INVALID_REQUEST, $e, 400, $invalidFields, $providerErrorDetails);
                    },
                ],
                // Default exception handling
                [
                    'default' => true,
                    'factory' => function (RequestException $e) {
                        if ($e->getResponse()) {
                            $statusCode = $e->getResponse()->getStatusCode();
                            // Classify by status code
                            if ($statusCode >= 500) {
                                return new LLMApiException(ErrorMessage::SERVER_ERROR . ': ' . $e->getMessage(), 3, $e, ErrorCode::API_SERVER_ERROR, $statusCode);
                            }
                            if ($statusCode >= 400) {
                                return new LLMApiException(ErrorMessage::CLIENT_ERROR . ': ' . $e->getMessage(), 2, $e, ErrorCode::API_INVALID_REQUEST, $statusCode);
                            }
                            // Other status codes are still treated as network exceptions, but record the status code
                            return new LLMNetworkException(ErrorMessage::NETWORK_REQUEST_ERROR . ': ' . $e->getMessage(), 4, $e, ErrorCode::NETWORK_CONNECTION_ERROR, $statusCode);
                        }
                        return new LLMNetworkException(ErrorMessage::NETWORK_REQUEST_ERROR . ': ' . $e->getMessage(), 4, $e, ErrorCode::NETWORK_CONNECTION_ERROR, 500);
                    },
                ],
            ],

            // Default exception handling
            'default' => [
                'factory' => function (Throwable $e) {
                    return new LLMException(ErrorMessage::LLM_INVOCATION_ERROR . ': ' . $e->getMessage(), 0, $e);
                },
            ],
        ];
    }
}

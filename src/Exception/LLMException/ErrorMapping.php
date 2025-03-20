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
            // 连接超时异常
            ConnectException::class => [
                // 连接超时异常
                [
                    'regex' => '/timeout|timed\s+out/i',
                    'factory' => function (Throwable $e) {
                        $message = $e->getMessage();
                        // 尝试从消息中提取超时时间
                        preg_match('/(\d+(?:\.\d+)?)\s*s/i', $message, $matches);
                        $timeout = isset($matches[1]) ? (float) $matches[1] : null;
                        return new LLMConnectionTimeoutException('连接LLM服务超时', $e, $timeout);
                    },
                ],
                // 无法解析主机名异常
                [
                    'regex' => '/Could not resolve host/i',
                    'factory' => function (Throwable $e) {
                        $message = $e->getMessage();
                        // 尝试从消息中提取主机名
                        preg_match('/Could not resolve host: ([^\s\(\)]+)/i', $message, $matches);
                        $hostname = isset($matches[1]) ? $matches[1] : '未知主机';
                        return new LLMNetworkException(
                            sprintf('无法解析LLM服务域名: %s', $hostname),
                            4,
                            $e,
                            ErrorCode::NETWORK_CONNECTION_ERROR
                        );
                    },
                ],
                // 默认网络连接异常处理
                [
                    'default' => true,
                    'factory' => function (Throwable $e) {
                        return new LLMNetworkException(
                            sprintf('LLM网络连接错误: %s', $e->getMessage()),
                            4,
                            $e,
                            ErrorCode::NETWORK_CONNECTION_ERROR
                        );
                    },
                ],
            ],

            // 请求异常
            RequestException::class => [
                // API密钥无效
                [
                    'regex' => '/invalid.+api.+key|api.+key.+invalid|authentication|unauthorized/i',
                    'status' => [401, 403],
                    'factory' => function (RequestException $e) {
                        $provider = '';
                        if ($e->getRequest()->getUri()->getHost()) {
                            $provider = $e->getRequest()->getUri()->getHost();
                        }
                        return new LLMInvalidApiKeyException('API密钥无效或已过期', $e, $provider);
                    },
                ],
                // 速率限制
                [
                    'regex' => '/rate\s+limit|too\s+many\s+requests/i',
                    'status' => [429],
                    'factory' => function (RequestException $e) {
                        $retryAfter = null;
                        if ($e->getResponse()) {
                            $retryAfter = $e->getResponse()->getHeaderLine('Retry-After');
                            $retryAfter = $retryAfter ? (int) $retryAfter : null;
                        }
                        return new LLMRateLimitException('API请求频率超出限制', $e, 429, $retryAfter);
                    },
                ],
                // 内容过滤
                [
                    'regex' => '/content\s+filter|content\s+policy|inappropriate|unsafe content|violate|policy/i',
                    'factory' => function (RequestException $e) {
                        $labels = null;
                        if ($e->getResponse()) {
                            $body = $e->getResponse()->getBody()->getContents();
                            $data = json_decode($body, true);
                            if (isset($data['error']['content_filter_results'])) {
                                $labels = array_keys($data['error']['content_filter_results']);
                            }
                        }
                        return new LLMContentFilterException('内容被系统安全过滤', $e, null, $labels);
                    },
                ],
                // 上下文长度超出限制
                [
                    'regex' => '/context\s+length|token\s+limit|maximum\s+context\s+length/i',
                    'factory' => function (RequestException $e) {
                        $currentLength = null;
                        $maxLength = null;
                        // 尝试从消息中提取长度信息
                        $message = $e->getMessage();
                        preg_match('/(\d+)\s*\/\s*(\d+)/i', $message, $matches);
                        if (isset($matches[1], $matches[2])) {
                            $currentLength = (int) $matches[1];
                            $maxLength = (int) $matches[2];
                        }
                        return new LLMContextLengthException('上下文长度超出模型限制', $e, null, $currentLength, $maxLength);
                    },
                ],
                // 多模态图片URL不可访问
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
                        return new LLMImageUrlAccessException('多模态图片URL不可访问', $e, null, $imageUrl);
                    },
                ],
                // 无效请求
                [
                    'regex' => '/invalid|bad\s+request/i',
                    'status' => [400],
                    'factory' => function (RequestException $e) {
                        $invalidFields = null;
                        if ($e->getResponse()) {
                            $body = $e->getResponse()->getBody()->getContents();
                            $data = json_decode($body, true);
                            if (isset($data['error']['param'])) {
                                $invalidFields = [$data['error']['param'] => $data['error']['message'] ?? '无效参数'];
                            }
                        }
                        return new LLMInvalidRequestException('无效的API请求', $e, 400, $invalidFields);
                    },
                ],
                // 默认异常处理
                [
                    'default' => true,
                    'factory' => function (RequestException $e) {
                        if ($e->getResponse()) {
                            $statusCode = $e->getResponse()->getStatusCode();
                            // 根据状态码分类
                            if ($statusCode >= 500) {
                                return new LLMApiException('LLM服务端错误: ' . $e->getMessage(), 3, $e, ErrorCode::API_SERVER_ERROR, $statusCode);
                            }
                            if ($statusCode >= 400) {
                                return new LLMApiException('LLM客户端请求错误: ' . $e->getMessage(), 2, $e, ErrorCode::API_INVALID_REQUEST, $statusCode);
                            }
                        }
                        return new LLMNetworkException('LLM网络请求错误: ' . $e->getMessage(), 4, $e, ErrorCode::NETWORK_CONNECTION_ERROR);
                    },
                ],
            ],

            // 默认异常处理
            'default' => [
                'factory' => function (Throwable $e) {
                    return new LLMException('LLM调用错误: ' . $e->getMessage(), 0, $e);
                },
            ],
        ];
    }
}

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
 * LLM错误码定义.
 */
class ErrorCode
{
    /**
     * 错误类型基数.
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

    /**
     * 错误码映射表.
     */
    public static function getErrorMessages(): array
    {
        return [
            // 配置错误
            self::CONFIG_INVALID_API_KEY => '无效的API密钥或API密钥缺失',
            self::CONFIG_INVALID_ENDPOINT => '无效的API终端点URL',
            self::CONFIG_INVALID_MODEL => '无效的模型名称或模型不可用',
            self::CONFIG_INVALID_PARAMETER => '无效的配置参数',

            // 网络错误
            self::NETWORK_CONNECTION_TIMEOUT => '连接LLM服务超时',
            self::NETWORK_READ_TIMEOUT => '从LLM服务读取响应超时',
            self::NETWORK_WRITE_TIMEOUT => '向LLM服务发送请求超时',
            self::NETWORK_CONNECTION_ERROR => '连接LLM服务失败',
            self::NETWORK_SSL_ERROR => 'SSL/TLS连接错误',

            // API错误
            self::API_RATE_LIMIT => 'API请求频率超出限制',
            self::API_INVALID_REQUEST => '无效的API请求',
            self::API_SERVER_ERROR => 'LLM服务端错误',
            self::API_AUTHENTICATION_ERROR => 'API认证失败',
            self::API_PERMISSION_DENIED => 'API权限不足',
            self::API_QUOTA_EXCEEDED => 'API配额已用尽',

            // 模型错误
            self::MODEL_CONTENT_FILTER => '内容被系统安全过滤',
            self::MODEL_CONTEXT_LENGTH => '上下文长度超出模型限制',
            self::MODEL_FUNCTION_CALL_NOT_SUPPORTED => '模型不支持函数调用功能',
            self::MODEL_MULTI_MODAL_NOT_SUPPORTED => '模型不支持多模态输入',
            self::MODEL_EMBEDDING_NOT_SUPPORTED => '模型不支持嵌入向量生成',
            self::MODEL_IMAGE_URL_ACCESS_ERROR => '多模态图片URL不可访问',
        ];
    }

    /**
     * 获取错误提示消息.
     */
    public static function getMessage(int $code): string
    {
        $messages = self::getErrorMessages();
        return $messages[$code] ?? '未知错误';
    }

    /**
     * 获取错误建议.
     */
    public static function getSuggestion(int $code): string
    {
        $suggestions = [
            // 配置错误建议
            self::CONFIG_INVALID_API_KEY => '请检查API密钥是否正确配置，或联系服务提供商获取有效的API密钥',
            self::CONFIG_INVALID_ENDPOINT => '请检查API终端点URL是否正确，确保包含协议前缀(http/https)',
            self::CONFIG_INVALID_MODEL => '请检查模型名称是否正确，或查询可用的模型列表',

            // 网络错误建议
            self::NETWORK_CONNECTION_TIMEOUT => '请检查网络连接或增加连接超时时间，稍后重试',
            self::NETWORK_READ_TIMEOUT => '请增加读取超时时间或减少请求复杂度，稍后重试',

            // API错误建议
            self::API_RATE_LIMIT => '请降低请求频率，实现请求节流或等待后重试',
            self::API_QUOTA_EXCEEDED => '请检查账户额度或升级账户计划',

            // 模型错误建议
            self::MODEL_CONTEXT_LENGTH => '请减少输入内容长度，或使用支持更长上下文的模型',
            self::MODEL_FUNCTION_CALL_NOT_SUPPORTED => '请选择支持函数调用功能的模型',
            self::MODEL_MULTI_MODAL_NOT_SUPPORTED => '请选择支持多模态输入的模型',
            self::MODEL_IMAGE_URL_ACCESS_ERROR => '请检查图片URL是否正确、可公开访问，并确保图片格式受支持',
        ];

        return $suggestions[$code] ?? '请检查输入参数和配置，如问题持续存在请联系技术支持';
    }
}

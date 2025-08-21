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

namespace Hyperf\Odin\Api\Providers\DashScope;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * DashScope 响应处理辅助类.
 *
 * 提供将 DashScope 响应转换为标准格式的静态方法
 */
class ResponseHandler
{
    /**
     * 转换DashScope响应数据为标准格式.
     * 
     * @param ResponseInterface $response 原始HTTP响应
     * @return ResponseInterface 转换后的响应
     */
    public static function convertResponse(ResponseInterface $response): ResponseInterface
    {
        $content = $response->getBody()->getContents();
        $data = json_decode($content, true);

        if (isset($data['usage'])) {
            $data['usage'] = self::convertUsageFields($data['usage']);
        }

        // 重新编码为JSON
        $newContent = json_encode($data);
        
        // 创建新的响应对象
        return new Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            $newContent
        );
    }

    /**
     * 转换DashScope的usage字段为标准格式.
     *
     * @param array $usage DashScope的usage数据
     * @return array 转换后的usage数据
     */
    public static function convertUsageFields(array $usage): array
    {
        // 处理 prompt_tokens_details
        if (isset($usage['prompt_tokens_details'])) {
            $usage['prompt_tokens_details'] = self::convertPromptTokensDetails($usage['prompt_tokens_details']);
        }

        return $usage;
    }

    /**
     * 转换 prompt_tokens_details 中的DashScope字段为标准字段.
     *
     * @param array $promptTokensDetails DashScope的prompt_tokens_details
     * @return array 转换后的prompt_tokens_details
     */
    private static function convertPromptTokensDetails(array $promptTokensDetails): array
    {
        $converted = $promptTokensDetails;

        // 1. 优先转换外层的 cache_creation_input_tokens -> cache_write_input_tokens
        if (isset($promptTokensDetails['cache_creation_input_tokens'])) {
            $converted['cache_write_input_tokens'] = $promptTokensDetails['cache_creation_input_tokens'];
        }
        // 2. 如果外层没有，再尝试从内层 cache_creation 获取
        elseif (isset($promptTokensDetails['cache_creation']['ephemeral_5m_input_tokens'])) {
            $converted['cache_write_input_tokens'] = $promptTokensDetails['cache_creation']['ephemeral_5m_input_tokens'];
        }

        // 3. 转换 cached_tokens（命中的缓存）
        // DashScope中的cached_tokens直接对应标准的cached_tokens，已经是标准字段，不需要转换
        
        // 4. 处理其他可能的DashScope字段到标准字段的映射
        // cache_type, cache_creation等保留为原始格式，不影响标准字段的使用

        return $converted;
    }
}

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

namespace Hyperf\Odin\Api\Providers\Gemini\Cache;

use Exception;

/**
 * Gemini 缓存客户端接口.
 * 定义缓存操作的统一接口，支持不同的认证方式实现.
 */
interface GeminiCacheClientInterface
{
    /**
     * 创建缓存.
     *
     * @param string $model 模型名称，支持以下格式：
     *                      - models/{model}
     *                      - projects/{project}/locations/{location}/publishers/{publisher}/models/{model}
     * @param array $config 缓存配置，包含 systemInstruction, tools, contents, ttl
     * @return array 缓存响应数据，包含 name 和 usageMetadata
     * @throws Exception
     */
    public function createCache(string $model, array $config): array;

    /**
     * 删除缓存.
     *
     * @param string $cacheName 缓存名称，支持以下格式：
     *                          - cachedContents/xxx
     *                          - projects/{project}/locations/{location}/cachedContents/{cacheId}
     * @throws Exception
     */
    public function deleteCache(string $cacheName): void;

    /**
     * 获取缓存信息.
     *
     * @param string $cacheName 缓存名称，支持以下格式：
     *                          - cachedContents/xxx
     *                          - projects/{project}/locations/{location}/cachedContents/{cacheId}
     * @return array 缓存信息
     * @throws Exception
     */
    public function getCache(string $cacheName): array;
}

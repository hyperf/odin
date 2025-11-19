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
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Providers\Gemini\GeminiConfig;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Gemini 缓存 API 客户端.
 * 封装缓存相关的 API 调用.
 */
class GeminiCacheClient
{
    private Client $client;

    private GeminiConfig $config;

    private ?LoggerInterface $logger;

    public function __construct(GeminiConfig $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->client = new Client([
            'base_uri' => $config->getBaseUrl(),
            'timeout' => 30,
        ]);
    }

    /**
     * 创建缓存.
     *
     * @param string $model 模型名称
     * @param array $config 缓存配置，包含 system_instruction, tools, contents, ttl
     * @return string 缓存名称（如 cachedContents/xxx）
     * @throws Exception
     */
    public function createCache(string $model, array $config): string
    {
        $url = $this->getBaseUri() . '/cachedContents';
        $body = [
            'model' => $model,
            'config' => $config,
        ];

        $options = [
            RequestOptions::JSON => $body,
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $this->logger?->debug('Creating Gemini cache', [
                'model' => $model,
                'url' => $url,
            ]);

            $response = $this->client->post($url, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);

            if (! isset($responseData['name'])) {
                throw new RuntimeException('Failed to create cache: missing name in response');
            }

            $this->logger?->info('Gemini cache created successfully', [
                'cache_name' => $responseData['name'],
                'model' => $model,
            ]);

            return $responseData['name'];
        } catch (Throwable $e) {
            $this->logger?->error('Failed to create Gemini cache', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            throw $e;
        }
    }

    /**
     * 删除缓存.
     *
     * @param string $cacheName 缓存名称（如 cachedContents/xxx）
     * @throws Exception
     */
    public function deleteCache(string $cacheName): void
    {
        $url = $this->getBaseUri() . '/' . $cacheName;

        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $this->logger?->debug('Deleting Gemini cache', [
                'cache_name' => $cacheName,
                'url' => $url,
            ]);

            $this->client->delete($url, $options);

            $this->logger?->info('Gemini cache deleted successfully', [
                'cache_name' => $cacheName,
            ]);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to delete Gemini cache', [
                'error' => $e->getMessage(),
                'cache_name' => $cacheName,
            ]);
            throw $e;
        }
    }

    /**
     * 获取缓存信息.
     *
     * @param string $cacheName 缓存名称（如 cachedContents/xxx）
     * @return array 缓存信息
     * @throws Exception
     */
    public function getCache(string $cacheName): array
    {
        $url = $this->getBaseUri() . '/' . $cacheName;

        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $response = $this->client->get($url, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to get Gemini cache', [
                'error' => $e->getMessage(),
                'cache_name' => $cacheName,
            ]);
            throw $e;
        }
    }

    /**
     * 更新缓存 TTL.
     *
     * @param string $cacheName 缓存名称（如 cachedContents/xxx）
     * @param array $config 更新配置，包含 ttl 或 expire_time
     * @throws Exception
     */
    public function updateCache(string $cacheName, array $config): void
    {
        $url = $this->getBaseUri() . '/' . $cacheName;

        $body = [
            'config' => $config,
        ];

        $options = [
            RequestOptions::JSON => $body,
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $this->client->patch($url, $options);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to update Gemini cache', [
                'error' => $e->getMessage(),
                'cache_name' => $cacheName,
            ]);
            throw $e;
        }
    }

    /**
     * 列出所有缓存.
     *
     * @return array 缓存列表
     * @throws Exception
     */
    public function listCaches(): array
    {
        $url = $this->getBaseUri() . '/cachedContents';

        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $response = $this->client->get($url, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);
            return $responseData['cachedContents'] ?? [];
        } catch (Throwable $e) {
            $this->logger?->error('Failed to list Gemini caches', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取认证头信息.
     */
    private function getHeaders(): array
    {
        $headers = [];
        if ($this->config->getApiKey()) {
            $headers['x-goog-api-key'] = $this->config->getApiKey();
        }
        return $headers;
    }

    /**
     * 获取基础 URI.
     */
    private function getBaseUri(): string
    {
        return rtrim($this->config->getBaseUrl(), '/');
    }
}

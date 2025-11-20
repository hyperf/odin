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
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
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

    public function __construct(GeminiConfig $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        // Build client options from ApiOptions
        $clientOptions = [
            'base_uri' => $config->getBaseUrl(),
            'timeout' => $apiOptions?->getTotalTimeout() ?? 30.0,
            'connect_timeout' => $apiOptions?->getConnectionTimeout() ?? 5.0,
        ];
        
        // Add proxy if configured
        if ($apiOptions && $apiOptions->hasProxy()) {
            $clientOptions['proxy'] = $apiOptions->getProxy();
        }
        
        $this->client = new Client($clientOptions);
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
        // Merge config fields directly into body according to Gemini API spec
        $body = array_merge(
            ['model' => $model],
            $config
        );

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

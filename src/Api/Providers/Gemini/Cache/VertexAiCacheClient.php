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
use Hyperf\Odin\Api\Providers\Gemini\ServiceAccountConfig;
use Hyperf\Odin\Api\Providers\Gemini\ServiceAccountCredentialsManager;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Vertex AI 缓存 API 客户端.
 * 使用 Service Account Key File 认证方式，适用于 Vertex AI Platform API.
 */
class VertexAiCacheClient implements GeminiCacheClientInterface
{
    private Client $client;

    private GeminiConfig $config;

    private ?LoggerInterface $logger;

    private ServiceAccountCredentialsManager $credentialsManager;

    /**
     * @param ServiceAccountConfig $serviceAccountConfig Service Account 配置
     */
    public function __construct(
        GeminiConfig $config,
        ServiceAccountConfig $serviceAccountConfig,
        ?ApiOptions $apiOptions = null,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->logger = $logger;

        // 初始化 Service Account 凭证管理器
        $this->credentialsManager = new ServiceAccountCredentialsManager(
            $serviceAccountConfig,
            $apiOptions,
            $logger
        );

        // Build HTTP client options from ApiOptions
        $httpClientOptions = [
            'timeout' => $apiOptions?->getTotalTimeout() ?? 30.0,
            'connect_timeout' => $apiOptions?->getConnectionTimeout() ?? 5.0,
        ];

        // Apply proxy configuration (supports HTTP/HTTPS and SOCKS5 proxies)
        if ($apiOptions && $apiOptions->hasProxy()) {
            $proxyConfig = $apiOptions->getGuzzleProxyConfig();
            $httpClientOptions = array_merge($httpClientOptions, $proxyConfig);
        }

        // Build cache API client options
        $clientOptions = array_merge(
            ['base_uri' => $config->getBaseUrl()],
            $httpClientOptions
        );

        $this->client = new Client($clientOptions);
    }

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
    public function createCache(string $model, array $config): array
    {
        // 构建缓存 API URL
        $url = $this->buildCacheUrl($model);

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
            $this->logger?->debug('Creating Vertex AI cache', [
                'model' => $model,
                'url' => $url,
                'request_body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);

            $response = $this->client->post($url, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);

            if (! isset($responseData['name'])) {
                throw new RuntimeException('Failed to create cache: missing name in response');
            }

            $cacheName = $responseData['name'];

            // Extract token usage from response if available
            $cacheTokens = null;
            if (isset($responseData['usageMetadata']['totalTokenCount'])) {
                $cacheTokens = $responseData['usageMetadata']['totalTokenCount'];
                $this->logger?->debug('Got cache tokens from create response', [
                    'cache_tokens' => $cacheTokens,
                ]);
            } else {
                // Fetch cache metadata to get usage information
                try {
                    $metadata = $this->getCache($cacheName);
                    if (isset($metadata['usageMetadata']['totalTokenCount'])) {
                        $cacheTokens = $metadata['usageMetadata']['totalTokenCount'];
                        $responseData['usageMetadata'] = $metadata['usageMetadata'];
                        $this->logger?->debug('Got cache tokens from metadata API', [
                            'cache_tokens' => $cacheTokens,
                        ]);
                    }
                } catch (Throwable $e) {
                    $this->logger?->warning('Failed to fetch cache metadata', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger?->info('Vertex AI cache created successfully', [
                'cache_name' => $cacheName,
                'cache_tokens' => $cacheTokens,
            ]);

            return $responseData;
        } catch (Throwable $e) {
            $this->logger?->error('Failed to create Vertex AI cache', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
            throw new Exception('Failed to create Vertex AI cache: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 删除缓存.
     *
     * @param string $cacheName 缓存名称，支持以下格式：
     *                          - cachedContents/xxx
     *                          - projects/{project}/locations/{location}/cachedContents/{cacheId}
     * @throws Exception
     */
    public function deleteCache(string $cacheName): void
    {
        // 如果是完整资源路径，直接使用；否则拼接 baseUri
        $url = $this->buildCacheResourceUrl($cacheName);

        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $this->logger?->debug('Deleting Vertex AI cache', [
                'cache_name' => $cacheName,
                'url' => $url,
            ]);

            $this->client->delete($url, $options);

            $this->logger?->info('Vertex AI cache deleted successfully', [
                'cache_name' => $cacheName,
            ]);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to delete Vertex AI cache', [
                'cache_name' => $cacheName,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to delete Vertex AI cache: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取缓存信息.
     *
     * @param string $cacheName 缓存名称，支持以下格式：
     *                          - cachedContents/xxx
     *                          - projects/{project}/locations/{location}/cachedContents/{cacheId}
     * @return array 缓存信息
     * @throws Exception
     */
    public function getCache(string $cacheName): array
    {
        // 如果是完整资源路径，直接使用；否则拼接 baseUri
        $url = $this->buildCacheResourceUrl($cacheName);

        $options = [
            RequestOptions::HEADERS => $this->getHeaders(),
        ];

        try {
            $this->logger?->debug('Getting Vertex AI cache info', [
                'cache_name' => $cacheName,
                'url' => $url,
            ]);

            $response = $this->client->get($url, $options);
            $cacheData = json_decode($response->getBody()->getContents(), true);

            $this->logger?->debug('Vertex AI cache info retrieved', [
                'cache_name' => $cacheName,
                'cache_data' => json_encode($cacheData, JSON_UNESCAPED_UNICODE),
            ]);

            return $cacheData;
        } catch (Throwable $e) {
            $this->logger?->error('Failed to get Vertex AI cache info', [
                'cache_name' => $cacheName,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to get Vertex AI cache info: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取认证头信息.
     * 使用 OAuth 2.0 Bearer token 认证.
     */
    private function getHeaders(): array
    {
        return $this->credentialsManager->getAuthHeaders();
    }

    /**
     * 获取基础 URI.
     */
    private function getBaseUri(): string
    {
        return rtrim($this->config->getBaseUrl(), '/');
    }

    /**
     * 获取 Vertex AI URL 组件 (scheme, host, version).
     *
     * @return array{scheme: string, host: string, version: string}
     */
    private function getVertexAiUrlComponents(): array
    {
        $baseUrl = parse_url($this->config->getBaseUrl());
        $scheme = $baseUrl['scheme'] ?? 'https';
        $host = $baseUrl['host'] ?? 'aiplatform.googleapis.com';

        // 提取版本号 (v1, v1beta1 等)
        $path = $baseUrl['path'] ?? '';
        $version = 'v1'; // 默认版本
        if (preg_match('#/(v\d+(?:beta\d+)?)[/]*#', $path, $versionMatches)) {
            $version = $versionMatches[1];
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'version' => $version,
        ];
    }

    /**
     * 根据 model 格式构建缓存 API URL.
     *
     * @param string $model 模型名称
     * @return string 缓存 API URL
     */
    private function buildCacheUrl(string $model): string
    {
        // 如果是完整资源路径格式: projects/{project}/locations/{location}/publishers/{publisher}/models/{model}
        if (preg_match('#^projects/([^/]+)/locations/([^/]+)/#', $model, $matches)) {
            $project = $matches[1];
            $location = $matches[2];

            // 构建 Vertex AI 缓存端点
            // URL 格式: {baseUrl}/projects/{project}/locations/{location}/cachedContents
            $components = $this->getVertexAiUrlComponents();

            return sprintf(
                '%s://%s/%s/projects/%s/locations/%s/cachedContents',
                $components['scheme'],
                $components['host'],
                $components['version'],
                $project,
                $location
            );
        }

        // 默认格式，使用 baseUri
        return $this->getBaseUri() . '/cachedContents';
    }

    /**
     * 根据 cacheName 格式构建缓存资源 URL (用于 get/delete 操作).
     *
     * @param string $cacheName 缓存名称
     * @return string 缓存资源 URL
     */
    private function buildCacheResourceUrl(string $cacheName): string
    {
        // 如果是完整资源路径格式: projects/{project}/locations/{location}/cachedContents/{cacheId}
        // 需要构建 Vertex AI 格式的完整 URL
        if (str_starts_with($cacheName, 'projects/')) {
            $components = $this->getVertexAiUrlComponents();

            // URL 格式: {scheme}://{host}/{version}/{cacheName}
            return sprintf(
                '%s://%s/%s/%s',
                $components['scheme'],
                $components['host'],
                $components['version'],
                $cacheName
            );
        }

        // 默认格式 (Generative Language API)，使用 baseUri + cacheName
        return $this->getBaseUri() . '/' . $cacheName;
    }
}

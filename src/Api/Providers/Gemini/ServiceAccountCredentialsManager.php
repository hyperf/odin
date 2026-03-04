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

namespace Hyperf\Odin\Api\Providers\Gemini;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Auth\HttpHandler\Guzzle7HttpHandler;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Throwable;

/**
 * Service Account 凭证管理器.
 * 统一管理 Service Account 认证，供缓存 API 和大模型 API 共享使用.
 */
class ServiceAccountCredentialsManager
{
    private ServiceAccountConfig $serviceAccountConfig;

    private ServiceAccountCredentials $credentials;

    private CacheInterface $cache;

    private ?LoggerInterface $logger;

    private Guzzle6HttpHandler|Guzzle7HttpHandler $httpHandler;

    /**
     * @param ServiceAccountConfig $serviceAccountConfig Service Account 配置
     * @param null|ApiOptions $apiOptions API 选项（用于 proxy 配置等）
     * @param null|LoggerInterface $logger 日志记录器
     */
    public function __construct(
        ServiceAccountConfig $serviceAccountConfig,
        ?ApiOptions $apiOptions = null,
        ?LoggerInterface $logger = null
    ) {
        $this->serviceAccountConfig = $serviceAccountConfig;
        $this->logger = $logger;

        // 初始化缓存
        $this->cache = ApplicationContext::getContainer()->get(CacheInterface::class);

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

        // 创建用于 OAuth Token 请求的 HTTP Handler
        // 使用带有 proxy 配置的 Guzzle Client
        $authHttpClient = new Client($httpClientOptions);
        $this->httpHandler = HttpHandlerFactory::build($authHttpClient);

        // 初始化 Service Account 凭证
        // 使用配置数组而不是文件路径，使用配置的 scopes
        $this->credentials = new ServiceAccountCredentials(
            $serviceAccountConfig->getScopes(),
            $serviceAccountConfig->toArray()
        );
    }

    /**
     * 获取 Access Token.
     * 使用缓存系统自动处理 token 缓存和刷新.
     *
     * @return string Access Token
     * @throws RuntimeException 当获取 token 失败时
     */
    public function getAccessToken(): string
    {
        // 生成缓存 key，基于整个 serviceAccountConfig 确保唯一性
        $cacheKey = 'vertex_ai_access_token:' . md5(serialize($this->serviceAccountConfig->toArray()));

        try {
            // 尝试从缓存获取 token
            $cachedToken = $this->cache->get($cacheKey);
            if ($cachedToken) {
                $this->logger?->debug('UsingCachedAccessToken', [
                    'cache_key' => $cacheKey,
                ]);
                return $cachedToken;
            }

            // 缓存未命中，获取新的 access token
            // 传入 httpHandler，使 token 请求也使用 proxy 配置
            $authToken = $this->credentials->fetchAuthToken($this->httpHandler);

            if (! isset($authToken['access_token'])) {
                throw new RuntimeException('Failed to fetch access token: missing access_token in response');
            }

            $accessToken = $authToken['access_token'];

            // 设置过期时间（默认 1 小时，提前 5 分钟刷新）
            $expiresIn = $authToken['expires_in'] ?? 3600;
            $cacheTtl = $expiresIn - 300; // 提前 5 分钟过期

            // 存入缓存
            $this->cache->set($cacheKey, $accessToken, $cacheTtl);

            $this->logger?->info('AccessTokenRefreshedAndCached', [
                'expires_in' => $expiresIn,
                'cache_ttl' => $cacheTtl,
                'expires_at' => date('Y-m-d H:i:s', time() + $expiresIn),
            ]);

            return $accessToken;
        } catch (Throwable $e) {
            $this->logger?->error('FailedToFetchAccessToken', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Failed to fetch access token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取认证头信息.
     * 使用 OAuth 2.0 Bearer token 认证.
     *
     * @return array 包含 Authorization 头的数组
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ];
    }
}

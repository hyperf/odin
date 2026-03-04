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

use InvalidArgumentException;

/**
 * Service Account 配置类.
 * 用于 Vertex AI Platform API 的 OAuth 2.0 认证.
 */
class ServiceAccountConfig
{
    private string $type;

    private string $projectId;

    private string $privateKeyId;

    private string $privateKey;

    private string $clientEmail;

    private string $clientId;

    private string $authUri;

    private string $tokenUri;

    private string $authProviderX509CertUrl;

    private string $clientX509CertUrl;

    private string $universeDomain;

    private array|string $scopes;

    public function __construct(
        string $projectId,
        string $privateKeyId,
        string $privateKey,
        string $clientEmail,
        string $clientId,
        string $type = 'service_account',
        string $authUri = 'https://accounts.google.com/o/oauth2/auth',
        string $tokenUri = 'https://oauth2.googleapis.com/token',
        string $authProviderX509CertUrl = 'https://www.googleapis.com/oauth2/v1/certs',
        string $clientX509CertUrl = '',
        string $universeDomain = 'googleapis.com',
        array|string $scopes = 'https://www.googleapis.com/auth/cloud-platform'
    ) {
        $this->type = $type;
        $this->projectId = $projectId;
        $this->privateKeyId = $privateKeyId;
        $this->privateKey = $privateKey;
        $this->clientEmail = $clientEmail;
        $this->clientId = $clientId;
        $this->authUri = $authUri;
        $this->tokenUri = $tokenUri;
        $this->authProviderX509CertUrl = $authProviderX509CertUrl;
        $this->clientX509CertUrl = $clientX509CertUrl;
        $this->universeDomain = $universeDomain;
        $this->scopes = $scopes;
    }

    /**
     * 从数组创建配置实例.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            projectId: $config['project_id'] ?? throw new InvalidArgumentException('project_id is required'),
            privateKeyId: $config['private_key_id'] ?? throw new InvalidArgumentException('private_key_id is required'),
            privateKey: $config['private_key'] ?? throw new InvalidArgumentException('private_key is required'),
            clientEmail: $config['client_email'] ?? throw new InvalidArgumentException('client_email is required'),
            clientId: $config['client_id'] ?? throw new InvalidArgumentException('client_id is required'),
            type: $config['type'] ?? 'service_account',
            authUri: $config['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth',
            tokenUri: $config['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            authProviderX509CertUrl: $config['auth_provider_x509_cert_url'] ?? 'https://www.googleapis.com/oauth2/v1/certs',
            clientX509CertUrl: $config['client_x509_cert_url'] ?? '',
            universeDomain: $config['universe_domain'] ?? 'googleapis.com',
            scopes: $config['scopes'] ?? 'https://www.googleapis.com/auth/cloud-platform'
        );
    }

    /**
     * 从 JSON 字符串创建配置实例.
     */
    public static function fromJson(string $json): self
    {
        $config = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return self::fromArray($config);
    }

    /**
     * 从 JSON 文件创建配置实例.
     */
    public static function fromFile(string $filePath): self
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Service Account Key file not found: {$filePath}");
        }

        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new InvalidArgumentException("Failed to read Service Account Key file: {$filePath}");
        }

        return self::fromJson($json);
    }

    /**
     * 转换为数组格式（兼容 Google Auth 库）.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'project_id' => $this->projectId,
            'private_key_id' => $this->privateKeyId,
            'private_key' => $this->privateKey,
            'client_email' => $this->clientEmail,
            'client_id' => $this->clientId,
            'auth_uri' => $this->authUri,
            'token_uri' => $this->tokenUri,
            'auth_provider_x509_cert_url' => $this->authProviderX509CertUrl,
            'client_x509_cert_url' => $this->clientX509CertUrl,
            'universe_domain' => $this->universeDomain,
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getPrivateKeyId(): string
    {
        return $this->privateKeyId;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getClientEmail(): string
    {
        return $this->clientEmail;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getAuthUri(): string
    {
        return $this->authUri;
    }

    public function getTokenUri(): string
    {
        return $this->tokenUri;
    }

    public function getAuthProviderX509CertUrl(): string
    {
        return $this->authProviderX509CertUrl;
    }

    public function getClientX509CertUrl(): string
    {
        return $this->clientX509CertUrl;
    }

    public function getUniverseDomain(): string
    {
        return $this->universeDomain;
    }

    public function getScopes(): array|string
    {
        return $this->scopes;
    }
}

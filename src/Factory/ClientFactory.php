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

namespace Hyperf\Odin\Factory;

use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrock;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsBedrockConfig;
use Hyperf\Odin\Api\Providers\AwsBedrock\AwsType;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Api\Providers\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAI;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Contract\Api\ClientInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 客户端工厂类，负责创建LLM API客户端实例.
 */
class ClientFactory
{
    /**
     * 创建OpenAI客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createOpenAIClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['base_url'] ?? '';
        $organization = $config['organization'] ?? '';

        // 创建配置对象
        $clientConfig = new OpenAIConfig(
            apiKey: $apiKey,
            organization: $organization,
            baseUrl: $baseUrl
        );

        // 创建API实例
        $openAI = new OpenAI();

        // 创建客户端
        return $openAI->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建Azure OpenAI客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createAzureOpenAIClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $apiKey = $config['api_key'] ?? '';
        $baseUrl = $config['api_base'] ?? $config['base_url'] ?? '';
        $apiVersion = $config['api_version'] ?? '2023-05-15';
        $deploymentName = $config['deployment_name'] ?? '';

        // 创建配置对象
        $clientConfig = new AzureOpenAIConfig(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            apiVersion: $apiVersion,
            deploymentName: $deploymentName
        );

        // 创建API实例
        $azureOpenAI = new AzureOpenAI();

        // 创建客户端
        return $azureOpenAI->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 创建AWS Bedrock客户端.
     *
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createAwsBedrockClient(array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        // 验证必要的配置参数
        $accessKey = $config['access_key'] ?? '';
        $secretKey = $config['secret_key'] ?? '';
        $region = $config['region'] ?? 'us-east-1';
        $type = $config['type'] ?? AwsType::CONVERSE;
        $autoCache = (bool) ($config['auto_cache'] ?? false);

        // 创建配置对象
        $clientConfig = new AwsBedrockConfig(
            accessKey: $accessKey,
            secretKey: $secretKey,
            region: $region,
            type: $type,
            autoCache: $autoCache
        );

        // 如果未提供API选项，则创建一个默认的选项
        if ($apiOptions === null) {
            $apiOptions = new ApiOptions();
        }

        // 创建API实例 - 使用正确的类结构
        $awsBedrock = new AwsBedrock();

        // 创建客户端
        return $awsBedrock->getClient($clientConfig, $apiOptions, $logger);
    }

    /**
     * 根据提供商类型创建客户端.
     *
     * @param string $provider 提供商类型 (openai, azure_openai, aws_bedrock)
     * @param array $config 配置参数
     * @param null|ApiOptions $apiOptions API请求选项
     * @param null|LoggerInterface $logger 日志记录器
     */
    public static function createClient(string $provider, array $config, ?ApiOptions $apiOptions = null, ?LoggerInterface $logger = null): ClientInterface
    {
        return match ($provider) {
            'openai' => self::createOpenAIClient($config, $apiOptions, $logger),
            'azure_openai' => self::createAzureOpenAIClient($config, $apiOptions, $logger),
            'aws_bedrock' => self::createAwsBedrockClient($config, $apiOptions, $logger),
            default => throw new InvalidArgumentException(sprintf('Unsupported provider: %s', $provider)),
        };
    }
}

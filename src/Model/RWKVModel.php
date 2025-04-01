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

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Api\Providers\OpenAI\OpenAI;
use Hyperf\Odin\Api\Providers\OpenAI\OpenAIConfig;
use Hyperf\Odin\Contract\Api\ClientInterface;

/**
 * RWKV模型实现.
 */
class RWKVModel extends AbstractModel
{
    /**
     * 获取RWKV客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        $openAI = new OpenAI();
        $config = new OpenAIConfig(
            apiKey: $config['api_key'] ?? '',
            organization: '', // RWKV不需要组织ID
            baseUrl: $config['base_url'] ?? 'http://localhost:8000'
        );
        return $openAI->getClient($config, $this->getApiRequestOptions(), $this->logger);
    }

    protected function getApiVersionPath(): string
    {
        return 'v1';
    }
}

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
 * Doubao模型实现.
 */
class DoubaoModel extends AbstractModel
{
    /**
     * 获取Doubao客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        $openAI = new OpenAI();
        $config = new OpenAIConfig(
            apiKey: $config['api_key'] ?? '',
            organization: '', // Doubao不需要组织ID
            baseUrl: $config['base_url'] ?? ''
        );
        return $openAI->getClient($config, $this->getApiRequestOptions(), $this->logger);
    }

    /**
     * 获取API版本路径.
     * Doubao的API版本路径为 api/v3.
     */
    protected function getApiVersionPath(): string
    {
        return 'api/v3';
    }
}

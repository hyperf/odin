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
 * ChatGLM模型实现.
 */
class ChatglmModel extends AbstractModel
{
    /**
     * 获取ChatGLM客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        $openAI = new OpenAI();
        $config = new OpenAIConfig(
            apiKey: $config['api_key'] ?? '',
            organization: '', // Chatglm不需要组织ID
            baseUrl: $config['base_url'] ?? 'http://localhost:8000'
        );
        return $openAI->getClient($config, $this->getApiRequestOptions(), $this->logger);
    }

    /**
     * 获取API版本路径.
     * ChatGLM的API版本路径为 api/paas/v4.
     */
    protected function getApiVersionPath(): string
    {
        return 'api/paas/v4';
    }
}

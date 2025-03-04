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
 * Ollama模型实现.
 */
class OllamaModel extends AbstractModel
{
    /**
     * 获取Ollama客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        $openAI = new OpenAI();
        $config = new OpenAIConfig(
            apiKey: $config['api_key'] ?? '', // Ollama不需要API Key
            organization: '', // Ollama不需要组织ID
            baseUrl: $config['base_url'] ?? 'http://0.0.0.0:11434',
            skipApiKeyValidation: true, // 显式标记Ollama不需要API Key验证
        );
        return $openAI->getClient($config, $this->getApiRequestOptions(), $this->logger);
    }

    protected function getApiVersionPath(): string
    {
        return 'v1';
    }
}

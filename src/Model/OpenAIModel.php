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

use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;
use Hyperf\Odin\Utils\ModelUtil;

/**
 * OpenAI模型实现.
 *
 * 支持智能路由：当使用qwen系列模型时，自动切换到DashScope客户端；
 * 其他模型继续使用OpenAI客户端。这确保了向后兼容性。
 */
class OpenAIModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    /**
     * 获取客户端实例，根据模型类型智能路由.
     * 如果是qwen系列模型，使用DashScope客户端；否则使用OpenAI客户端.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        // 检查是否为qwen系列模型
        if (ModelUtil::isQwenModel($this->model)) {
            // 使用ClientFactory统一创建DashScope客户端
            return ClientFactory::createClient(
                'dashscope',
                $config,
                $this->getApiRequestOptions(),
                $this->logger
            );
        }

        // 使用ClientFactory统一创建OpenAI客户端
        return ClientFactory::createClient(
            'openai',
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    /**
     * 获取API版本路径.
     * OpenAI的API版本路径为 v1.
     */
    protected function getApiVersionPath(): string
    {
        return 'v1';
    }
}

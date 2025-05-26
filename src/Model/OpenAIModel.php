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

/**
 * OpenAI模型实现.
 */
class OpenAIModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    /**
     * 获取OpenAI客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        // 使用ClientFactory创建OpenAI客户端
        return ClientFactory::createOpenAIClient(
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

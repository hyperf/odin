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
 * AWS Bedrock模型实现.
 */
class AwsBedrockModel extends AbstractModel
{
    /**
     * 获取AWS Bedrock客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // AWS Bedrock不需要处理API基础URL
        $config = $this->config;

        // 使用ClientFactory创建AWS Bedrock客户端
        return ClientFactory::createAwsBedrockClient(
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    /**
     * 获取API版本路径.
     * AWS Bedrock无需API版本路径.
     */
    protected function getApiVersionPath(): string
    {
        return '';
    }
}

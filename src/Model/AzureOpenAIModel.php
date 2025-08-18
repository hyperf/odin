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
 * Azure OpenAI模型实现.
 */
class AzureOpenAIModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    protected array $chatCompletionRequestOptionKeyMaps = [
        'max_tokens' => 'max_completion_tokens',
    ];

    /**
     * 获取Azure OpenAI客户端实例.
     */
    protected function getClient(): ClientInterface
    {
        // Azure OpenAI通过Client自己处理URL路径，不需要使用processApiBaseUrl
        // 因为它的URL结构比较特殊: {endpoint}/openai/deployments/{deployment-id}/chat/completions?api-version={api-version}

        // 使用ClientFactory创建AzureOpenAI客户端
        return ClientFactory::createAzureOpenAIClient(
            $this->config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }
}

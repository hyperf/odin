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
 * DeepSeek model implementation.
 *
 * Supports both standard chat and reasoning (thinking) mode.
 * To enable thinking mode, set the thinking parameter in ChatCompletionRequest:
 * $request->setThinking(['type' => 'enabled']);
 *
 * Key features:
 * - Support for multi-turn tool calling with reasoning_content preservation
 * - Compatible with OpenAI API format
 *
 * Note: The reasoning_content handling logic is implemented in the Client layer.
 *
 * @see https://api-docs.deepseek.com/zh-cn/guides/thinking_mode
 */
class DeepSeekModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    /**
     * Get client instance.
     */
    protected function getClient(): ClientInterface
    {
        $config = $this->config;
        $this->processApiBaseUrl($config);

        return ClientFactory::createClient(
            'deepseek',
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    /**
     * Get API version path.
     * DeepSeek's API version path is v1.
     */
    protected function getApiVersionPath(): string
    {
        return 'v1';
    }
}
